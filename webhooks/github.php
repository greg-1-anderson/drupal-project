<?php

/**
 * This script receives webhook requests initiated from GitHub.
 * GitHub should be set up to send a request to a Patheon multidev
 * environment named 'build' every time something is pushed to the
 * GitHub repository's master branch.
 *
 * This script will then in turn pull the code and, if composer.lock
 * has been properly committed, will run 'composer install' and commit
 * the result back to the 'fat' repository.  From here, it can be
 * deployed to live as usual.
 *
 *    User             GitHub              Multidev           Test
 *      |                |                   |                  |
 *      |---- push ----> |                   |                  |
 *      |                |                   |                  |
 *      |                |----- webhook ---->|                  |
 *      |                |                   |                  |
 *      |                | +---- pull -------|                  |
 *      |                | +->               |                  |
 *      |                |                   |-------+          |
 *      |                |                   |       |          |
 *      |                |                   |    composer      |
 *      |                |                   |    install       |
 *      |                |                   |       |          |
 *      |                |                   |<------+          |
 *      |                |                   |                  |
 *      |                |                   |----- deploy ---->|
 *      |                |                   |                  |
 *
 * The multidev environment remains in SFTP mode so that it can
 * pull from the remote repository and run `composer install`.
 *
 * By default, the 'build' multidev environment builds everything
 * that is pushed from the 'master' branch; other multidev environments
 * assume that the remote branch name is the same as the local branch
 * name (which is the same as the multidev environment name).
 *
 * Test and live should never be targets.
 */
$repositoryRoot = dirname(__DIR__);
$bindingDir = dirname($repositoryRoot); // or $_SERVER['HOME']
$composerRoot = "$projectRoot/private";

include_once "$repositoryRoot/private/scripts/pantheon/lean-repo-utils.php";

// Determine which local branch we are going to merge into.
$localBranch = $_ENV['PANTHEON_ENVIRONMENT'];
if ($localBranch == 'dev') {
  $localBranch = 'master';
}
// Silently do nothing if this is 'test' or 'live'.
if (in_array($localBranch, array('test', 'live'))) {
  exit(0);
}

// Fetch our secret data / parameters
$secrets = pantheon_get_secrets($bindingDir, ['lean-repo'], ['lean-gh-token' => '', 'lean-remote-branch' => '']);

$githubUrl = $secrets['lean-repo']; // e.g. https://github.com/joshkoenig/lean-and-mean.git';
$githubToken = $secrets['lean-gh-token'];
$remoteBranch = $secrets['lean-remote-branch'];
if (empty($githubUrl)) {
  pantheon_raise_dashboard_error('Secrets file does not contain a Github URL.');
}
if (!empty($githubToken)) {
  $githubUrl = str_replace('https://',
                           'https://'. $githubToken . ':x-oauth-basic@',
                           $githubUrl);
}
/*
else {
  pantheon_raise_dashboard_error('No Github Token found');
}
*/

// Figure out what the remote branch should be.  This is usually going
// to be the environment name (or 'master') for dev, but the 'build'
// multidev also builds from master, and you may target any remote
// branch you wish by setting an appropriate entry in the secrets file.
if (empty($remoteBranch)) {
  $remoteBranch = $localBranch == 'build' ? 'master' : $localBranch;
}

chdir($repositoryRoot);
pantheon_process_github_webhook($githubUrl);

// If composer.lock has been committed, then run `composer install`
// Note that `composer update` requires too much memory to run here,
// and `composer install` without a lock file behaves equivalently.
if (file_exists("$composerRoot/composer.lock")) {
  chdir($composerRoot);
  exec('composer install', $composerInstallOutput, $status);
  if ($status) {
    pantheon_raise_dashboard_error('Composer install failed.', $composerInstallOutput);
  }
  pantheon_commit_build_results($repositoryRoot);
}

// Push merged lean changes up to Pantheon's internal repo.
// This will trigger a sync_code event.
exec("git push origin $localBranch", $gitPushOutput, $status);
print_r($gitPushOutput);

function pantheon_commit_build_results($repositoryRoot) {
  // Prepare to commit build results.
  // The composer-generated files -could- be added with --force,
  // but we still need to know which files to add.  Therefore,
  // we will instead modify the .gitignore file to permit the
  // addition of the generated files, and then put it back
  // again when we are done.  This allows us to use the .gitignore
  // file to list which directories contain build results.
  // There is a marker in the .gitignore that separates the
  // build results entries, which should be added to the Pantheon
  // repository, but should not be added to the lean repository,
  // from all of the other .gitignore entries which should never be
  // added to either repository.  Everything above the marker are
  // build results.  We get rid of these entries temporarily when
  // adding to the Pantheon repository.  We also add '.gitignore' to
  // the top of the file, so that we do not add the .gitignore file itself
  // to the Pantheon reposiotry during this step.
  $gitignoreFile = "$repositoryRoot/.gitignore";
  $gitignoreContents = file_get_contents($gitignoreFile);
  $markerPos = strpos($gitignoreContents, "### Persistent .gitignore entries:");
  if (strpos($gitignoreContents, $marker) !== FALSE) {
    $reducedContents = ".gitignore\n\n" . substr($gitignoreContents, 0, $markerPos);
    file_put_contents($gitignoreFile, $reducedContents);
    // Commit build results
    exec('git add -A .', $gitAddOutput, $status);
    if ($status) {
      pantheon_raise_dashboard_error('Git add failed.', $gitAddOutput);
    }
    exec('git commit -m "Commit build results."', $gitCommitOutput, $status);
    if ($status) {
      pantheon_raise_dashboard_error('Git commit failed.', $gitCommitOutput);
    }
    // restore gitignore. We could also run `git checkout -- .gitignore`
    file_put_contents($gitignoreFile, $gitignoreContents);
  }
}


/**
 * Process a webhook received from GitHub.
 */
function pantheon_process_github_webhook($remoteUrl, $remoteBranch) {
  try {
    // Parse the POST data.
    $payload = json_decode(file_get_contents('php://input'), 1);
    // Only execute if we have a github push.
    if (!isset($_SERVER['HTTP_X_HUB_SIGNATURE'])) {
      pantheon_raise_dashboard_error('No GitHub signature header');
    }
    // Fetch the master branch from the remote URL.  Put it in the
    // '_lean_upstream' branch in the local repository, creating it
    // if necessary.
    exec("git fetch $remoteUrl $remoteBranch:_lean_upstream", $gitFetchOutput, $status);
    if ($status !== 0) {
      pantheon_raise_dashboard_error("Error fetching from GitHub - $status", $gitFetchOutput);
    }
    // Merge the _lean_upstream branch into the current Multidev
    // branch.  Use -Xtheirs, so that any conflict is satisfied in
    // favor of the code coming in from the remote repository.
    exec('git merge -s recursive -Xtheirs _lean_upstream -m "From upstream: '. $payload['head_commit']['message'] .'"', $gitMergeOutput, $status);
    if ($status == 128) {
      pantheon_raise_dashboard_error("Uncommitted changes present - Merge blocked", $gitMergeOutput);
    }
    elseif ($status !== 0) {
      pantheon_raise_dashboard_error("Merge error - $status", $gitMergeOutput);
    }
  }
  catch (Exception $e) {
    // Try and emit an error message to the dashboard if there was a fail.
    pantheon_raise_dashboard_error($e->getMessage(), $e);
  }
}

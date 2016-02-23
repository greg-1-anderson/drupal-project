<?php
/***
 * This script will attempt to push "lean" changes back upstream.
***/
$repositoryRoot = dirname(dirname(dirname(__DIR__)));
$bindingDir = dirname($repositoryRoot); // or $_SERVER['HOME']

include_once "$repositoryRoot/private/scripts/pantheon/lean-repo-utils.php";

if (!in_array($_ENV['PANTHEON_ENVIRONMENT'], array('test', 'live'))) {
  // Examine incoming commit to see if anything should go upstream.
  $lean_files = array();
  $non_lean_files = array();
  $SHA = trim(`git rev-parse HEAD 2>&1`);
  // Get all edited files in the commit we just recievd in sync_code.
  echo "Looking for files to push upstream...\n";
  #echo "git diff --name-only HEAD HEAD~1";
  #echo `cd $workspace && git diff HEAD HEAD~1`;
  #echo `git diff --name-only HEAD HEAD~1`;
  #echo `git rev-parse HEAD~1`;
  exec('git diff --name-only HEAD HEAD~1 2>&1', $output, $status);
  foreach ($output as $file) {
    if (`git cat-file _lean_upstream:$file -e` === NULL) {
      $lean_files[] = $file;
    }
    else {
      $non_lean_files[] = $file;
    }
  }
  if (count($lean_files) > 0 && count($non_lean_files) > 0) {
    // We crossed the streams.
    pantheon_raise_dashboard_error('Mixed commit fail!');
  }
  if (count($lean_files) > 0 && count($non_lean_files) == 0) {
    // Push the most recent hash.
    echo "Last commit was just files tracked upstream:\n\n";
    echo implode("\n", $lean_files);
    echo "\n\nPushing...\n";
    echo "\ncd $workspace && git push . $SHA:_lean_upstream 2>&1\n";
    echo `cd $workspace && git push . $SHA:_lean_upstream 2>&1`;
    echo "\ngit push $remote_url _lean_upstream:master 2>&1\n";
    echo `git push $remote_url _lean_upstream:master 2>&1`;
  }
  else {
    echo "No commits to push back upstream. All is well.";
  }
  // TODO, handle other branches
  // echo `git symbolic-ref -q HEAD`;
}

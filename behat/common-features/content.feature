Feature: Content
  In order to test some basic Behat functionality
  As a website user
  I need to be able to see that the Drupal and Drush drivers are working

  @api
  Scenario: Create nodes with specific authorship
    Given users:
    | name      | mail             | status |
    | User Fred | fred@example.com | 1      |
    And "article" content:
    | title           | author    | promote |
    | Article by Fred | User Fred | 1       |
    When I am logged in as a user with the "administrator" role
    And I am on the homepage
    And I follow "Article by Fred"
    Then I should see "Article by Fred"
    And I should see the link "User Fred"

  @api
  Scenario: Create a node
    Given I am logged in as a user with the "administrator" role
    When I am viewing an "article" content with the title "My article"
    Then I should see the heading "My article"

  @api
  Scenario: Create many nodes
    Given "page" content:
    | title    |
    | Page one |
    | Page two |
    And "article" content:
    | title          |
    | First article  |
    | Second article |
    And I am logged in as a user with the "administrator" role
    When I go to "admin/content"
    Then I should see "Page one"
    And I should see "Page two"
    And I should see "First article"
    And I should see "Second article"

  @api
  Scenario: Create nodes with fields
    Given "article" content:
    | title                     | promote | body             |
    | First article with fields |       1 | PLACEHOLDER BODY |
    When I am on the homepage
    And follow "First article with fields"
    Then I should see the text "PLACEHOLDER BODY"


# "Given I am viewing" does not seem to view the created article

#  @api
#  Scenario: Create and view a node with fields
#    Given I am viewing an "Article" content:
#    | title | My article with fields  |
#    | body  | A placeholder           |
#    Then I should see the heading "My article with fields"
#    And I should see the text "A placeholder"

  @api
  Scenario: Create users
    Given users:
    | name     | mail            | status |
    | Joe User | joe@example.com | 1      |
    And I am logged in as a user with the "administrator" role
    When I visit "admin/people"
    Then I should see the link "Joe User"

  @api
  Scenario: Login as a user created during this scenario
    Given users:
    | name      | status | mail             |
    | Test user |      1 | test@example.com |
    When I am logged in as "Test user"
    Then I should see the link "Log out"

  @api
  Scenario: Create a term
    Given I am logged in as a user with the "administrator" role
    When I am viewing a "tags" term with the name "My tag"
    Then I should see the heading "My tag"

  @api
  Scenario: Create many terms
    Given "tags" terms:
    | name    |
    | Tag one |
    | Tag two |
    And I am logged in as a user with the "administrator" role
    When I go to "admin/structure/taxonomy/manage/tags/overview"
    Then I should see "Tag one"
    And I should see "Tag two"



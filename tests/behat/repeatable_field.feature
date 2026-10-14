@customfield @customfield_repeatable @javascript
Feature: Repeatable sets course custom field
  In order to keep structured repeatable data on a course
  As a manager and a teacher
  I need to define the columns in the field's dialogue, fill sets on the course form and see them in the course listing

  Background:
    Given the following "users" exist:
      | username | firstname | lastname  | email                |
      | teacher1 | Teacher   | Example 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "custom field categories" exist:
      | name              | component   | area   | itemid |
      | Category for test | core_course | course | 0      |

  Scenario: Columns defined in the dialogue, two sets saved on the course and shown in the listing
    Given I log in as "admin"
    And I navigate to "Courses > Default settings > Course custom fields" in site administration
    And I click on "Add a new custom field" "link"
    And I click on "Repeatable sets" "link"
    And I set the following fields to these values:
      | Name       | Languages |
      | Short name | languages |
    And I press "Add column"
    And I set the field "Column name" to "Language"
    And I click on "Save changes" "button" in the "Adding a new Repeatable sets" "dialogue"
    And I should see "Languages"
    And I log out
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Settings" in current page administration
    And I expand all fieldsets
    # Each set's input is labelled "Language" (and aria-labelled "Language (Set N)"): address it by id.
    And I set the field "id_customfield_languages_1_1" to "Portuguese"
    And I press "Add new set"
    And I set the field "id_customfield_languages_2_1" to "Spanish"
    And I press "Save and display"
    And I am on course index
    Then I should see "Languages" in the ".customfield_languages .customfieldname" "css_element"
    And I should see "Portuguese" in the ".customfield_languages .customfieldvalue" "css_element"
    And I should see "Spanish" in the ".customfield_languages .customfieldvalue" "css_element"

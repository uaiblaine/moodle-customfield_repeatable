@customfield @customfield_repeatable @core_reportbuilder @javascript
Feature: One row per set in a custom report of course repeatable sets
  In order to report on the sets of a repeatable course custom field
  As an admin
  I need a custom report source that lists every set of every course as its own row

  Background:
    Given the following "custom field categories" exist:
      | name              | component   | area   | itemid |
      | Category for test | core_course | course | 0      |
    And the following "custom fields" exist:
      | name      | category          | type       | shortname | configdata                                                                                                               |
      | Languages | Category for test | repeatable | languages | {"schema":"{\"version\":2,\"columns\":[{\"key\":\"language\",\"label\":\"Language\",\"mode\":\"free\",\"title\":true}]}"} |
    And the following "courses" exist:
      | fullname | shortname | customfield_languages                                                   |
      | Course 1 | C1        | {"@@ID#1":{"language":"Portuguese"},"@@ID#2":{"language":"Spanish"}} |
      | Course 2 | C2        | {"@@ID#1":{"language":"French"}}                                       |

  Scenario: A report created from the course sets source shows one row per set
    Given I log in as "admin"
    And I change window size to "large"
    When I navigate to "Reports > Report builder > Custom reports" in site administration
    And I click on "New report" "button"
    And I set the following fields in the "New report" "dialogue" to these values:
      | Name                  | Course languages       |
      | Report source         | Course repeatable sets |
      | Include default setup | 1                      |
    And I click on "Save" "button" in the "New report" "dialogue"
    And I click on "Add column 'Set number'" "link"
    And I click on "Add column 'Language'" "link"
    And I am on the "Course languages" "reportbuilder > View" page
    Then the following should exist in the "reportbuilder-table" table:
      | Course full name with link | Set number | Language   |
      | Course 1                   | 1          | Portuguese |
      | Course 1                   | 2          | Spanish    |
      | Course 2                   | 1          | French     |

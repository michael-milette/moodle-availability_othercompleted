@availability @availability_othercompleted
Feature: availability_othercompleted
  In order to control student access to activities based on other course completion
  As a teacher
  I need to set course completion conditions which prevent student access

  Background:
    Given the following "courses" exist:
      | fullname       | shortname | format | enablecompletion |
      | Test course 1  | TC1       | topics | 1                |
      | Test course 2  | TC2       | topics | 1                |
      | Test course 3  | TC3       | topics | 1                |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TC1    | editingteacher |
      | teacher1 | TC2    | editingteacher |
      | teacher1 | TC3    | editingteacher |
      | student1 | TC1    | student        |
      | student1 | TC2    | student        |
      | student1 | TC3    | student        |
    And the following "activities" exist:
      | activity | course | name           | completion |
      | page     | TC1    | TC1 Page 1     | 1          |
      | page     | TC1    | TC1 Page 2     | 1          |
      | page     | TC2    | TC2 Page 1     | 1          |
      | page     | TC2    | TC2 Page 2     | 1          |
      | page     | TC3    | TC3 Activity 1 |            |
      | page     | TC3    | TC3 Activity 2 |            |
      | page     | TC3    | TC3 Activity 3 |            |

  @javascript
  Scenario: Test course completion restriction with othercompleted condition
    # Set up course completion for TC1 - require both activities
    Given I am on the "Test course 1" "course" page logged in as "teacher1"
    And I navigate to "Course completion" in current page administration
    And I click on "Condition: Activity completion" "link"
    And I set the field "Page - TC1 Page 1" to "1"
    And I set the field "Page - TC1 Page 2" to "1"
    And I press "Save changes"

    # Set up course completion for TC2 - require both activities
    And I am on the "Test course 2" "course" page
    And I navigate to "Course completion" in current page administration
    And I click on "Condition: Activity completion" "link"
    And I set the field "Page - TC2 Page 1" to "1"
    And I set the field "Page - TC2 Page 2" to "1"
    And I press "Save changes"

    # Set up restrictions on TC3 activities
    # Activity 1: Show when TC1 is complete
    And I am on the "TC3 Activity 1" "page activity editing" page
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "Other course completion" "button" in the "Add restriction..." "dialogue"
    And I set the following fields to these values:
      | Required completion status | must be marked complete |
      | cm                         | Test course 1                 |
    And I press "Save and return to course"

    # Activity 2: Show when TC1 is NOT complete
    And I am on the "TC3 Activity 2" "page activity editing" page
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "Other course completion" "button" in the "Add restriction..." "dialogue"
    And I set the following fields to these values:
      | Required completion status | must be marked complete |
      | cm                         | Test course 1           |
      | Restriction type           | must not                |
    And I press "Save and return to course"

    # Activity 3: Hidden when TC1 is NOT complete (shown when complete)
    And I am on the "TC3 Activity 3" "page activity editing" page
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "Other course completion" "button" in the "Add restriction..." "dialogue"
    And I click on ".availability-item .availability-eye img" "css_element"
    And I set the following fields to these values:
      | Required completion status | must be marked complete |
      | cm                         | Test course 1                 |
    And I press "Save and return to course"

    # Verify restrictions are shown to teacher
    Then I should see "Not available unless: You have completed course Test course 1" in the "TC3 Activity 1" "activity"
    And I should see "Not available unless: You have incompleted course Test course 1" in the "TC3 Activity 2" "activity"
    And I should see "Not available unless: You have completed course Test course 1 (hidden otherwise)" in the "TC3 Activity 3" "activity"

    # Log in as student and verify initial state (TC1 not complete)
    When I am on the "Test course 3" "course" page logged in as "student1"
    Then I should see "Not available unless: You have completed course Test course 1" in the "region-main" "region"
    And I should see "TC3 Activity 2" in the "region-main" "region"
    And I should not see "TC3 Activity 3" in the "region-main" "region"

    # Complete TC1 Page 1
    And I am on the "Test course 1" "course" page
    And I toggle the manual completion state of "TC1 Page 1"
    And I am on the "Test course 3" "course" page
    Then I should see "Not available unless: You have completed course Test course 1" in the "region-main" "region"
    And I should see "TC3 Activity 2" in the "region-main" "region"
    And I should not see "TC3 Activity 3" in the "region-main" "region"

    # Complete TC1 Page 2 (this should complete the course)
    And I am on the "Test course 1" "course" page
    And I toggle the manual completion state of "TC1 Page 2"
    And I run all adhoc tasks

    # Verify TC3 restrictions now show correctly (TC1 complete)
    And I am on the "Test course 3" "course" page
    Then I should see "TC3 Activity 1" in the "region-main" "region"
    And I should see "TC3 Activity 1" in the "region-main" "region"
    And I should not see "Not available unless: You have incompleted course Test course 1" in the "TC3 Activity 1" "activity"
    And I should see "Not available unless: You have incompleted course Test course 1" in the "TC3 Activity 2" "activity"
    And I should see "TC3 Activity 3" in the "region-main" "region"

  @javascript
  Scenario: Test multiple course completion restrictions
    # Set up course completion for TC1
    Given I am on the "Test course 1" "course" page logged in as "teacher1"
    And I navigate to "Course completion" in current page administration
    And I click on "Condition: Activity completion" "link"
    And I set the field "Page - TC1 Page 1" to "1"
    And I set the field "Page - TC1 Page 2" to "1"
    And I press "Save changes"

    # Set up course completion for TC2
    And I am on the "Test course 2" "course" page
    And I navigate to "Course completion" in current page administration
    And I click on "Condition: Activity completion" "link"
    And I set the field "Page - TC2 Page 1" to "1"
    And I set the field "Page - TC2 Page 2" to "1"
    And I press "Save changes"

    # Set up TC3 Activity 1 with both TC1 and TC2 completion requirements
    And I am on the "TC3 Activity 1" "page activity editing" page
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "Other course completion" "button" in the "Add restriction..." "dialogue"
    And I set the following fields to these values:
      | Required completion status | must be marked complete |
      | cm                         | Test course 1                 |
    And I click on "Add restriction..." "button"
    And I click on "Other course completion" "button" in the "Add restriction..." "dialogue"
    And I set the field with xpath "//div[contains(concat(' ', normalize-space(@class), ' '), ' availability-item ')][preceding-sibling::div]//select[@name='cm']" to "Test course 2"
    And I press "Save and return to course"

    # Verify both restrictions show
    Then I should see "Not available unless:" in the "TC3 Activity 1" "activity"
    And I click on "Show more" "button" in the "TC3 Activity 1" "activity"
    And I should see "You have completed course Test course 1" in the "TC3 Activity 1" "activity"
    And I should see "You have completed course Test course 2" in the "TC3 Activity 1" "activity"

    # Student should not see the activity initially
    When I am on the "Test course 3" "course" page logged in as "student1"
    Then I should see "Not available unless:" in the "region-main" "region"

    # Complete TC1
    And I am on the "Test course 1" "course" page
    And I toggle the manual completion state of "TC1 Page 1"
    And I toggle the manual completion state of "TC1 Page 2"
    And I run all adhoc tasks
    And I am on the "Test course 3" "course" page
    Then I should see "Not available unless:" in the "region-main" "region"

    # Complete TC2
    And I am on the "Test course 2" "course" page
    And I toggle the manual completion state of "TC2 Page 1"
    And I toggle the manual completion state of "TC2 Page 2"
    And I run all adhoc tasks

    # Activity should now be available
    And I am on the "Test course 3" "course" page
    Then I should see "TC3 Activity 1" in the "region-main" "region"
    And I should not see "Not available unless:" in the "region-main" "region"

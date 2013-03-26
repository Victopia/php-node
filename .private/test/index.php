<?php
/* Test.php | Run this to ensure basic settings are correct. */

require_once('.private/test/BasicSetupIntegrityTest.php');
require_once('.private/test/DatabaseTest.php');

require_once('.private/test/simpletest/reporter.php');

$test = new BasicSetupIntegrityTest();

// $test->run(new HtmlReporter());

$test = new DatabaseTest();

$test->run(new HtmlReporter());
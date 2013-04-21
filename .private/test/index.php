<?php
/* Test.php | Run this to ensure basic settings are correct. */

require_once('.private/test/BasicSetupIntegrityTest.php');
require_once('.private/test/DatabaseTest.php');

require_once('.private/test/simpletest/reporter.php');

$reporter = new HtmlReporter();

$tests = array(
  'BasicSetupIntegrityTest'
, 'DatabaseTest'
);

foreach ($tests as $test) {
  (new $test())->run($reporter);
}
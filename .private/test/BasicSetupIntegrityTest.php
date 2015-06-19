<?php
/* test/BasicSetupIntegrityTest.php | Test against basic system setups. */

require_once('.private/test/simpletest/unit_tester.php');

use framework\System;

class BasicSetupIntegrityTest extends UnitTestCase {

  //--------------------------------------------------
  //
  //  Private properties
  //
  //--------------------------------------------------

  private $dummyFile;

  //--------------------------------------------------
  //
  //  Test methods
  //
  //--------------------------------------------------

  function testCreateDummyFile() {
    $this->assertTrue(is_writable(System::getPathname()),
      'PHP does not have the permission to write to gateway directory, please ensure corresponding permissions.');

    $this->dummyFile = tempnam(System::getPathname(), 'TEST_');

    $this->assertTrue(file_exists($this->dummyFile),
      'Dummy file cannot be created, please make sure PHP can write files in the gateway directory.');

    $this->assertTrue(rename($this->dummyFile, $this->dummyFile . '.php'),
      'Dummy file cannot be renamed, please check appropriate permissions to the gateway directory.');

    $this->dummyFile.= '.php';

    $this->assertTrue(is_writable($this->dummyFile),
      'Dummy file is not writable, please check appropriate permission to the gateway directory.');
  }

  function testWriteToDummyFile() {
    $fileContents = @file_put_contents($this->dummyFile, '<?php echo "1"; ?>');

    $this->assertTrue($fileContents && $fileContents > 0,
      'Error writing to dummy file.');
  }

  function testFileResolver() {
    core\Net::httpRequest(array(
      'url' => (@$_SERVER['HTTPS'] ? 'https' : 'http') . "://$_SERVER[HTTP_HOST]" . FRAMEWORK_PATH_VIRTUAL . DIRECTORY_SEPARATOR . basename($this->dummyFile)
    , 'callbacks' => array(
        'success' => function($response) {
          $this->assertEqual($response, 1,
            'Incorrect response rendered from dummy file, please check PHP handler on gateway directory.');
        }
      , 'failure' => function() {
          $this->assertTrue(FALSE,
            'Unable to invoke HTTP request to dummy file, this is usually due to incorrect configuration of FRAMEWORK_PATH_VIRTUAL or running the test with an unresolvable local hostname.');
        }
      )
    ));
  }

  function __destruct() {
    @unlink($this->dummyFile);
  }

}

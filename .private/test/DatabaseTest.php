<?php
/* test/DatabaseTest.php | Test against database connections and tables setup. */

require_once('.private/test/simpletest/unit_tester.php');

class DatabaseTest extends UnitTestCase {

  //--------------------------------------------------
  //
  //  Test methods
  //
  //--------------------------------------------------

  function testConnection() {
    $this->ignoreException('PDOException');

    $this->assertTrue(core\Database::isConnected(),
      'Not connected to database, please check database configurations.');

    $res = core\Database::query('SELECT 1');

    $this->assertIsA($res, 'PDOStatement',
      'Database returned object is not a proper PDOStatement.');

    $res = $res->fetchAll(\PDO::FETCH_COLUMN, 0);

    $res = @$res[0][0];

    $this->assertEqual($res, 1,
      'Unable to fetch data from PDOStatement.');
  }

  // function testSchemas() { // $tables = core\Database::query(); }

}
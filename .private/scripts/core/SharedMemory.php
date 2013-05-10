<?php
/* SharedMemory.php | Wrapper class that utilitze shared memory functions. */

namespace core;

/**
 * SharedMemory class.
 *
 * Variables stored in this class is stored in the shared memory segment.
 */
class SharedMemory {

  private $ipcFile;

  private $ipc;

  //--------------------------------------------------
  //
  //  Constructor
  //
  //--------------------------------------------------

  public function __construct($channel) {
    $this->ipcFile = sys_get_temp_dir() . "/$channel.ipc";

    @touch($this->ipcFile);

    $this->ipc = ftok($this->ipcFile, 's'); // s for shm

    $this->ipc = shm_attach($this->ipc); // leave $memsize to php.ini
  }

  //--------------------------------------------------
  //
  //  Methods
  //
  //--------------------------------------------------

  /**
   * @private
   *
   * Generates a rather unique integer version from a string.
   *
   * Not likely to have collisions on normal word-based variable name usage.
   */
  public function intkey($key) {
    return substr(base_convert(md5($key), 16, 10) , -5);
  }

  /**
   * Remove the shared memory segment.
   */
  public function destroy() {
    $res = shm_remove($this->ipc);

    @unlink($this->ipcFile);
  }

  //--------------------------------------------------
  //
  //  Overloads
  //
  //--------------------------------------------------

  public function __get($name) {
    $name = $this->intkey($name);

    if (shm_has_var($this->ipc, $name)) {
      return shm_get_var($this->ipc, $name);
    }

    return NULL;
  }

  public function __set($name, $data) {
    $name = $this->intkey($name);

    shm_put_var($this->ipc, $name, $data);
  }

  public function __unset($name) {
    $name = $this->intkey($name);

    shm_remove_var($this->ipc, $name);
  }
}
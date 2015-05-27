<?php

/**
 * Reference to a file or block of file data which can be uploaded using
 * @{class:ArcanistFileUploader}.
 *
 * You can either upload a file on disk by using @{method:setPath}, or upload
 * a block of data in memory by using @{method:setData}.
 *
 * For usage examples, see @{class:ArcanistFileUploader}.
 *
 * After uploading, successful uploads will have @{method:getPHID} populated.
 * Failed uploads will have @{method:getErrors} populated with a description
 * of reasons for failure.
 *
 * @task config Configuring File References
 * @task results Handling Upload Results
 * @task uploader Uploader API
 */
final class ArcanistFileDataRef extends Phobject {

  private $name;
  private $data;
  private $path;
  private $hash;
  private $size;
  private $errors = array();
  private $phid;
  private $fileHandle;


/* -(  Configuring File References  )---------------------------------------- */


  /**
   * @task config
   */
  public function setName($name) {
    $this->name = $name;
    return $this;
  }


  /**
   * @task config
   */
  public function getName() {
    return $this->name;
  }


  /**
   * @task config
   */
  public function setData($data) {
    $this->data = $data;
    return $this;
  }


  /**
   * @task config
   */
  public function getData() {
    return $this->data;
  }


  /**
   * @task config
   */
  public function setPath($path) {
    $this->path = $path;
    return $this;
  }


  /**
   * @task config
   */
  public function getPath() {
    return $this->path;
  }


/* -(  Handling Upload Results  )-------------------------------------------- */


  /**
   * @task results
   */
  public function getErrors() {
    return $this->errors;
  }


  /**
   * @task results
   */
  public function getPHID() {
    return $this->phid;
  }


/* -(  Uploader API  )------------------------------------------------------- */


  /**
   * @task uploader
   */
  public function willUpload() {
    $have_data = ($this->data !== null);
    $have_path = ($this->path !== null);

    if (!$have_data && !$have_path) {
      throw new Exception(
        pht(
          'Specify setData() or setPath() when building a file data '.
          'reference.'));
    }

    if ($have_data && $have_path) {
      throw new Exception(
        pht(
          'Specify either setData() or setPath() when building a file data '.
          'reference, but not both.'));
    }

    if ($have_path) {
      $path = $this->path;

      if (!Filesystem::pathExists($path)) {
        throw new Exception(
          pht(
            'Unable to upload file: path "%s" does not exist.',
            $path));
      }

      try {
        Filesystem::assertIsFile($path);
      } catch (FilesystemException $ex) {
        throw new Exception(
          pht(
            'Unable to upload file: path "%s" is not a file.',
            $path));
      }

      try {
        Filesystem::assertReadable($path);
      } catch (FilesystemException $ex) {
        throw new Exception(
          pht(
            'Unable to upload file: path "%s" is not readable.',
            $path));
      }

      $hash = @sha1_file($path);
      if ($hash === false) {
        throw new Exception(
          pht(
            'Unable to upload file: failed to calculate file data hash for '.
            'path "%s".',
            $path));
      }

      $size = @filesize($path);
      if ($size === false) {
        throw new Exception(
          pht(
            'Unable to upload file: failed to determine filesize of '.
            'path "%s".',
            $path));
      }

      $this->hash = $hash;
      $this->size = $size;
    } else {
      $data = $this->data;
      $this->hash = sha1($data);
      $this->size = strlen($data);
    }
  }


  /**
   * @task uploader
   */
  public function didFail($error) {
    $this->errors[] = $error;
    return $this;
  }


  /**
   * @task uploader
   */
  public function setPHID($phid) {
    $this->phid = $phid;
    return $this;
  }


  /**
   * @task uploader
   */
  public function getByteSize() {
    if ($this->size === null) {
      throw new PhutilInvalidStateException('willUpload');
    }
    return $this->size;
  }


  /**
   * @task uploader
   */
  public function getContentHash() {
    if ($this->size === null) {
      throw new PhutilInvalidStateException('willUpload');
    }
    return $this->hash;
  }


  /**
   * @task uploader
   */
  public function didUpload() {
    if ($this->fileHandle) {
      @fclose($this->fileHandle);
      $this->fileHandle = null;
    }
  }


  /**
   * @task uploader
   */
  public function readBytes($start, $end) {
    if ($this->size === null) {
      throw new PhutilInvalidStateException('willUpload');
    }

    $len = ($end - $start);

    if ($this->data !== null) {
      return substr($this->data, $start, $len);
    }

    $path = $this->path;

    if ($this->fileHandle === null) {
      $f = @fopen($path, 'rb');
      if (!$f) {
        throw new Exception(
          pht(
            'Unable to upload file: failed to open path "%s" for reading.',
            $path));
      }
      $this->fileHandle = $f;
    }

    $f = $this->fileHandle;

    $ok = @fseek($f, $start);
    if ($ok !== 0) {
      throw new Exception(
        pht(
          'Unable to upload file: failed to fseek() to offset %d in file '.
          'at path "%s".',
          $start,
          $path));
    }

    $data = @fread($f, $len);
    if ($data === false) {
      throw new Exception(
        pht(
          'Unable to upload file: failed to read %d bytes after offset %d '.
          'from file at path "%s".',
          $len,
          $start,
          $path));
    }

    return $data;
  }

}
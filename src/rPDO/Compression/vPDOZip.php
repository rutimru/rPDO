<?php
/**
 * Этот файл является частью пакета rPDO.
 *
 * Авторское право (c) Vitaly Surkov <surkov@rutim.ru>
 *
 * Для получения полной информации об авторских правах и лицензии, пожалуйста, ознакомьтесь с LICENSE
 * файл, который был распространен с этим исходным кодом.
 */

namespace rPDO\Compression;

use rPDO\rPDO;

/**
 * Представляет собой сжатый архив в формате ZIP.
 *
 * @package rPDO/Compression
 */
class rPDOZip {
    const CREATE = 'create';
    const OVERWRITE = 'overwrite';
    const ZIP_TARGET = 'zip_target';
    const ALLOWED_EXTENSIONS = 'allowed_extensions';
    const EXCLUDE = 'exclude';

    public $rpdo = null;
    protected $_filename = '';
    protected $_options = array();
    protected $_archive = null;
    protected $_exclusions = array();
    protected $_errors = array();

    /**
     * Construct an instance representing a specific archive.
     *
     * @param rPDO &$xpdo A reference to an xPDO instance.
     * @param string $filename The name of the archive the instance will represent.
     * @param array $options An array of options for this instance.
     */
    public function __construct(rPDO &$rpdo, $filename, array $options = array()) {
        $this->rpdo =& $rpdo;
        $this->_filename = is_string($filename) ? $filename : '';
        $this->_options = is_array($options) ? $options : array();
        $this->_archive = new \ZipArchive();
        if (!empty($this->_filename) && file_exists(dirname($this->_filename))) {
            if (file_exists($this->_filename)) {
                if ($this->getOption(rPDOZip::OVERWRITE, null, false) && is_writable($this->_filename)) {
                    if ($this->_archive->open($this->_filename, \ZipArchive::OVERWRITE) !== true) {
                        $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "xPDOZip: Error opening archive at {$this->_filename} for OVERWRITE");
                    }
                } else {
                    if ($this->_archive->open($this->_filename) !== true) {
                        $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "xPDOZip: Error opening archive at {$this->_filename}");
                    }
                }
            } elseif ($this->getOption(rPDOZip::CREATE, null, false) && is_writable(dirname($this->_filename))) {
                if ($this->_archive->open($this->_filename, \ZipArchive::CREATE) !== true) {
                    $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "xPDOZip: Could not create archive at {$this->_filename}");
                }
            } else {
                $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "xPDOZip: The location specified is not writable: {$this->_filename}");
            }
        } else {
            $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "xPDOZip: The location specified does not exist: {$this->_filename}");
        }
    }

    /**
     * Pack the contents from the source into the archive.
     *
     * @todo Implement custom file filtering options
     *
     * @param string $source The path to the source file(s) to pack.
     * @param array $options An array of options for the operation.
     * @return array An array of results for the operation.
     */
    public function pack($source, array $options = array()) {
        $results = array();
        if ($this->_archive) {
            $target = $this->getOption(rPDOZip::ZIP_TARGET, $options, '');
            if (is_dir($source)) {
                if ($dh = opendir($source)) {
                    if ($source[strlen($source) - 1] !== '/') $source .= '/';
                    $targetDir = rtrim($target, '/');
                    if (!empty($targetDir)) {
                        if ($this->_archive->addEmptyDir($targetDir)) {
                            $results[$target] = "Successfully added directory {$target} from {$source}";
                        } else {
                            $results[$target] = "Error adding directory {$target} from {$source}";
                            $this->_errors[] = $results[$target];
                        }
                    }
                    while (($file = readdir($dh)) !== false) {
                        if ($this->checkExclude($target . $file, $options)) {
                            if (is_dir($source . $file)) {
                                if (($file !== '.') && ($file !== '..')) {
                                    $results = $results + $this->pack($source . $file . '/', array_merge($options, array(rPDOZip::ZIP_TARGET => $target . $file . '/')));
                                }
                            } elseif (is_file($source . $file)) {
                                if ($this->_archive->addFile($source . $file, $target . $file)) {
                                    $results[$target . $file] = "Successfully packed {$target}{$file} from {$source}{$file}";
                                } else {
                                    $results[$target . $file] = "Error packing {$target}{$file} from {$source}{$file}";
                                    $this->_errors[] = $results[$target . $file];
                                }
                            } else {
                                $results[$target . $file] = "Error packing {$target}{$file} from {$source}{$file}";
                                $this->_errors[] = $results[$target . $file];
                            }
                        }
                    }
                }
            } elseif (is_file($source)) {
                $file = basename($source);
                if ($this->checkExclude($target . $file, $options)) {
                    if ($this->_archive->addFile($source, $target . $file)) {
                        $results[$target . $file] = "Successfully packed {$target}{$file} from {$source}";
                    } else {
                        $results[$target . $file] = "Error packing {$target}{$file} from {$source}";
                        $this->_errors[] = $results[$target . $file];
                    }
                }
            } else {
                $this->_errors[]= "Invalid source specified: {$source}";
            }
        }
        return $results;
    }

    /**
     * Unpack the compressed contents from the archive to the target.
     *
     * @param string $target The path of the target location to unpack the files.
     * @param array $options An array of options for the operation.
     * @return bool|array An array of results for the operation.
     */
    public function unpack($target, $options = array()) {
        $results = false;
        if ($this->_archive) {
            if (is_dir($target) && is_writable($target)) {
                if (isset($options[self::ALLOWED_EXTENSIONS]) && is_array($options[self::ALLOWED_EXTENSIONS])) {
                    $fileIndex = array();
                    for ($i = 0; $i < $this->_archive->numFiles; $i++) {
                        $filename = $this->_archive->getNameIndex($i);
                        if ($this->checkFiletype($filename, $options[self::ALLOWED_EXTENSIONS])) {
                            $fileIndex[] = $filename;
                        }
                    }
                    $results = $this->_archive->extractTo($target, $fileIndex);
                } else {
                    $results = $this->_archive->extractTo($target);
                }
            }
        }
        return $results;
    }

    /**
     * Close the archive.
     */
    public function close() {
        if ($this->_archive) {
            $this->_archive->close();
        }
    }

    /**
     * Check files exclude regular expression.
     * 
     * @param string $target
     * @param array $options
     * @return bool
     */
    protected function checkExclude($target, $options = array()) {
        $allow = true;
        if (empty($this->_exclusions)) {
            $exclude = $this->getOption(rPDOZip::EXCLUDE, $options, null);
            switch (true) {
                case (is_string($exclude)):
                    $exclude = explode(',', $exclude);
                case (is_array($exclude)):
                    if (!$exclude = array_diff(array_map('trim', $exclude), array(''))) {
                        $exclude = null;
                    }
            }
            $this->_exclusions = $exclude;
        }
        if (!empty($this->_exclusions)) {
            foreach ($this->_exclusions as $v) {
                if (preg_match('/' . $v . '/u', $target)) {
                    $allow = false;
                }
            }
        }
        return $allow;
    }

    /**
     * Get an option from supplied options, the xPDOZip instance options, or xpdo itself.
     *
     * @param string $key Unique identifier for the option.
     * @param array $options A set of explicit options to override those from xPDO or the
     * xPDOZip instance.
     * @param mixed $default An optional default value to return if no value is found.
     * @return mixed The value of the option.
     */
    public function getOption($key, $options = null, $default = null) {
        $option = $default;
        if (is_array($key)) {
            if (!is_array($option)) {
                $default= $option;
                $option= array();
            }
            foreach ($key as $k) {
                $option[$k]= $this->getOption($k, $options, $default);
            }
        } elseif (is_string($key) && !empty($key)) {
            if (is_array($options) && !empty($options) && array_key_exists($key, $options)) {
                $option = $options[$key];
            } elseif (is_array($this->_options) && !empty($this->_options) && array_key_exists($key, $this->_options)) {
                $option = $this->_options[$key];
            } else {
                $option = $this->rpdo->getOption($key, null, $default);
            }
        }
        return $option;
    }

    /**
     * Detect if errors occurred during any operations.
     *
     * @return boolean True if errors exist, otherwise false.
     */
    public function hasError() {
        return !empty($this->_errors);
    }

    /**
     * Return any errors from operations on the archive.
     */
    public function getErrors() {
        return $this->_errors;
    }

    /**
     * Check that the filename has a file type extension that is allowed
     *
     * @param string $filename The filename
     * @param array $allowedExtensions The allowed file type extensions
     * @return bool
     */
    private function checkFiletype($filename, $allowedExtensions)
    {
        if (is_array($allowedExtensions)) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $ext = strtolower($ext);
            if (!in_array($ext, $allowedExtensions)) {
                $this->rpdo->log(rPDO::LOG_LEVEL_WARN, $filename .' can\'t be extracted, because the file type is not allowed.');
                return false;
            }
        }
        return true;
    }
}

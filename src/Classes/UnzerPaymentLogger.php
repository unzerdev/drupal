<?php

namespace Drupal\commerce_unzerpayment\Classes;
class UnzerPaymentLogger
{

  /**
   * @var UnzerpaymentLogger
   */
  public static $instance = false;

  /**
   * @var array
   */
  private static $loglevels = [
    1 => 'ERROR',
    2 => 'INFO',
    3 => 'DEBUG'
  ];

  /**
   * loglevel = 1 ... logs only errors
   * loglevel = 2 ... logs more information
   * loglevel = 3 ... debug level, everything logged
   *
   * @var int
   */
  private $loglevel = 1;

  /**
   * UnzerpaymentLogger constructor.
   */
  public function __construct()
  {
    //$this->loglevel = (int)\Configuration::get('UNZERPAYMENT_LOGLEVEL');
  }

  /**
   * Add message to Logfile, if level critical also to PS-log table
   *
   * @param $message
   * @param int $loglevel
   * @param \Exception $exception|false
   * @param array $dataarray
   */
  public function addLog(
    $message,
    $loglevel = 3,
    $exception = false,
    $dataarray = []
  ) {

    if ($this->loglevel >= $loglevel) {
      $backtrace = debug_backtrace();
      $fileinfo = '';
      $callsinfo = '';
      if (!empty($backtrace[0]) && is_array($backtrace[0])) {
        $fileinfo = $backtrace[0]['file'] . ": " . $backtrace[0]['line'];
        for ($x=1; $x<5; $x++) {
          if (!empty($backtrace[$x]) && is_array($backtrace[$x])) {
            if (isset($backtrace[$x]['file']) && is_array($backtrace[$x]['file'])) {
              $callsinfo.= "\r\n" . $backtrace[$x]['file'] . ": " . $backtrace[$x]['line'];
            }
          }
        }
      }
      $logstr = date("Y-m-d H:i:s");
      $logstr.= ' [' . self::$loglevels[$loglevel] . '] ';
      $logstr.= $message;
      $logstr.= ' - ' . $fileinfo;
      $logstr.= "\r\n";
      $logstr.= 'URL: ' . $_SERVER['REQUEST_URI'];
      $logstr.= "\r\n";
      if ($callsinfo != '') {
        $logstr.= 'Backtrace :';
        $logstr.= $callsinfo . "\r\n";
      }
      $this->writeToLogfile($logstr);
      if ($exception) {
        $exceptionlog = 'Exception thrown: ';
        $exceptionlog.= $exception->getCode() . ': ' . $exception->getMessage() . ' - ';
        $exceptionlog.= $exception->getFile() . ': ' . $exception->getLine();
        $exceptionlog.= "\r\n";
        $this->writeToLogfile($exceptionlog);
      }
      if (sizeof($dataarray) > 0) {
        $arraylog = 'Data-Array :';
        $arraylog.= "\r\n";
        $arraylog.= print_r($dataarray, true);
        $arraylog.= "\r\n";
        $this->writeToLogfile($arraylog);
      }
    }
  }

  public function writeToLogfile(string $logdata)
  {
    \Drupal::logger('unzerpayment')->error($logdata);
  }

  /**
   * @return UnzerpaymentLogger
   */
  public static function getInstance()
  {
    if (!self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

}

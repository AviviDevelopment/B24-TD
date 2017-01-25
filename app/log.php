<?//if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$lodDir=$_SERVER['DOCUMENT_ROOT'].'/TDoctor/'.'logs';

//define('LOG_DIR', $_SERVER['DOCUMENT_ROOT'].'/timedoctor/'.'logs');
//define('DEBUG', true);

/**
 * CB24Log
 */

/**
 * Логирование отладочной информации
 *
 * Пример использования:
 * \cnLog::Add('string message'.print_r($someVar, true));
 * Для очистки лога:
 * \cnLog::Add('some required text', true);
 * @static
 */
class CB24Log {

	private $active=false;

	private function __construct() {}
	/**
	 * Запись в лог
	 * @param string  $msg   текст, который будет записан в лог
	 * @param boolean $clean если установлено - очистит лог-файл
	 */
	public static function Add($msg, $clean=false) {
		$lodDir=$_SERVER['DOCUMENT_ROOT'].'/TDoctor/'.'logs';
//
		if ($lodDir && is_string($msg)) {
			$DATE = date('Y-m-d H:i:s');
			$strLogFile = date('Y-m-d').".log";
			$strCalledFrom = '';
			if (function_exists('debug_backtrace')) {
				$locations = debug_backtrace();
				$strCalledFrom = 'F: '.$locations[0]['file']. "\n" . '      L: '.$locations[0]['line'];
			}
			$logMsg = "\n" .
				'date: ' . $DATE . "\n" .
				'mess: ' . $msg . "\n" .
				'from: ' . $strCalledFrom . "\n" .
				'uri : ' . $_SERVER['REQUEST_URI'] . "\n" .
				'----------------------------------------------------------';
			if ($clean) {
				CB24Log::CleanLog($strLogFile);
			}
			CB24Log::AppendLog($logMsg, $strLogFile);
		}
	}

	/**
	 * Функция добавления новой записи в лог
	 * @param string $msg текст записи
	 */
	private static function AppendLog($msg, $filename) {
		$lodDir=$_SERVER['DOCUMENT_ROOT'].'/TDoctor/'.'logs';

		$log_file = $lodDir.'/'.$filename;
		$mode = 'ab';

		if (!file_exists($log_file)) $mode = 'x';
//
		if ($fp = fopen($lodDir.'/'.$filename, $mode)) {
			fwrite($fp, $msg);
			fclose($fp);
		}
	}

	/**
	 * Удаление лог-файла
	 */
	private static function CleanLog($filename) {
		$lodDir=$_SERVER['DOCUMENT_ROOT'].'/TDoctor/'.'logs';

		@unlink($lodDir.'/'.$filename);
	}

	/**
	 * Получение лог-файла
	 */
	public static function GetLog($filename) {
		$lodDir=$_SERVER['DOCUMENT_ROOT'].'/TDoctor/'.'logs';

		$log = false;
		if ($fp = fopen($lodDir.'/'.$filename, 'rb')) {
			$log = fread($fp);
			fclose($fp);
		}
		return $log;
	}

}


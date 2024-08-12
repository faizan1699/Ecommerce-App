<?php
/* ------------------------------ NOTICE ----------------------------------

If you're seeing this text when browsing to the installer, it means your
web server is not set up properly.

Please contact your host and ask them to enable "PHP" processing on your
account.
----------------------------- NOTICE --------------------------------- */
namespace Duplicator\Installer\Bootstrap {
use Duplicator\Installer\Utils\SecureCsrf;
use Duplicator\Libs\DupArchive\DupArchive;
use Duplicator\Libs\DupArchive\DupArchiveExpandBasicEngine;
use Duplicator\Libs\Shell\Shell;
use Exception;
use InstallerBootstrapData;
use ZipArchive;
class BootstrapRunner
{
    const MINIMUM_PHP_VERSION = '5.6.20';
    const NAME_PWD        = 'password';
    const NAME_PWD_BUTTON = 'secure-btn';
    const ZIP_MODE_NONE    = -1;
    const ZIP_MODE_AUTO    = 0;
    const ZIP_MODE_ARCHIVE = 1;
    const ZIP_MODE_SHELL   = 2;
    const CSRF_KEY_ARCHIVE_PASSWORD = 'arc_pwd';
    protected $archive             = '';
    protected $bootloader          = '';
    protected $archiveFileName     = '';
    protected $archiveFileSize     = 0;
    protected $installerDirName    = '';
    protected $packageHash         = '';
    protected $secondaryHash       = '';
    protected $version             = '';
    protected $archivePwd          = '';
    protected $extractionTmpFolder = '';
    public $targetRoot            = null;
    public $origDupInstFolder     = null;
    public $targetDupInstFolder   = null;
    public $targetDupInst         = null;
    public $manualExtractFileName = null;
    public $isCustomDupFolder     = false;
    public $hasZipArchive         = false;
    public $mainInstallerURL      = '';
    public $archiveExpectedSize   = 0;
    public $archiveActualSize     = 0;
    public $archiveRatio          = 0;
    protected $errorMessage        = '';
    protected $installerFilesFound = 0;
    private static $instance = null;
    protected function __construct()
    {
        if (!class_exists(InstallerBootstrapData::class)) {
            throw new Exception('Class InstallerBootstrapData must be defined');
        }
        $this->archiveFileName  = InstallerBootstrapData::ARCHIVE_FILENAME;
        $this->archiveFileSize  = InstallerBootstrapData::ARCHIVE_SIZE;
        $this->installerDirName = InstallerBootstrapData::INSTALLER_DIR_NAME;
        $this->packageHash      = InstallerBootstrapData::PACKAGE_HASH;
        $this->secondaryHash    = InstallerBootstrapData::SECONDARY_PACKAGE_HASH;
        $this->version          = InstallerBootstrapData::VERSION;
        $this->setHTTPHeaders();
        $this->targetRoot = BootstrapUtils::setSafePath(dirname(__FILE__));
        $this->log('', true);
        $archive_filepath          = $this->getArchiveFilePath();
        $this->origDupInstFolder   = $this->installerDirName;
        $this->targetDupInstFolder = filter_input(INPUT_GET, 'dup_folder', FILTER_SANITIZE_SPECIAL_CHARS, array(
            "options" => array(
                "default" => $this->installerDirName,
            ),
            'flags'   => FILTER_FLAG_STRIP_HIGH));
        $this->isCustomDupFolder     = $this->origDupInstFolder !== $this->targetDupInstFolder;
        $this->targetDupInst         = $this->targetRoot . '/' . $this->targetDupInstFolder;
        $this->manualExtractFileName = 'dup-manual-extract__' . $this->packageHash;
        if ($this->isCustomDupFolder) {
            $this->extractionTmpFolder = $this->getTempDir($this->targetRoot);
        } else {
            $this->extractionTmpFolder = $this->targetRoot;
        }
        SecureCsrf::init($this->targetDupInst, $this->packageHash);
        $archiveActualSize         = @file_exists($archive_filepath) ? @filesize($archive_filepath) : false;
        $archiveActualSize         = ($archiveActualSize !== false) ? $archiveActualSize : 0;
        $this->archiveExpectedSize = (is_numeric($this->archiveFileSize) ? (int) $this->archiveFileSize : 0);
        $this->archiveActualSize   = $archiveActualSize;
        if ($this->archiveExpectedSize > 0) {
            $this->archiveRatio = (((1.0) * $this->archiveActualSize) / $this->archiveExpectedSize) * 100;
        } else {
            $this->archiveRatio = 100;
        }
    }
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    public static function initSetValues()
    {
        define('KB_IN_BYTES', 1024);
        define('MB_IN_BYTES', 1024 * KB_IN_BYTES);
        define('GB_IN_BYTES', 1024 * MB_IN_BYTES);
        define('DUPLICATOR_PRO_PHP_MAX_MEMORY', 4096 * MB_IN_BYTES);
        date_default_timezone_set('UTC'); // Some machines don’t have this set so just do it here.
        @ignore_user_abort(true);
        @set_time_limit(3600);
        if (BootstrapUtils::isIniValChangeable('memory_limit')) {
            @ini_set('memory_limit', DUPLICATOR_PRO_PHP_MAX_MEMORY);
        }
        if (BootstrapUtils::isIniValChangeable('max_input_time')) {
            @ini_set('max_input_time', '-1');
        }
        if (BootstrapUtils::isIniValChangeable('pcre.backtrack_limit')) {
            @ini_set('pcre.backtrack_limit', PHP_INT_MAX);
        }
        if (BootstrapUtils::isIniValChangeable('default_socket_timeout')) {
            @ini_set('default_socket_timeout', 3600);
        }
    }
    private function setHTTPHeaders()
    {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
    }
    private function getTempDir($path)
    {
        $tempfile = tempnam($path, 'dup-installer_tmp_');
        if (file_exists($tempfile)) {
            unlink($tempfile);
            mkdir($tempfile);
            if (is_dir($tempfile)) {
                return $tempfile;
            }
        }
        return false;
    }
    public function run()
    {
        date_default_timezone_set('UTC'); // Some machines don't have this set so just do it here
        $this->log('==DUPLICATOR INSTALLER BOOTSTRAP v' . $this->version . '==');
        $this->log('----------------------------------------------------');
        $this->log('Installer bootstrap start');
        $archive_filepath = $this->getArchiveFilePath();
        $this->log('Target dir dup folder \"' . $this->targetDupInst . '\"');
        $this->errorMessage = '';
        $is_installer_file_valid = true;
        if (preg_match('/_([a-z0-9]{7})[a-z0-9]+_[0-9]{6}([0-9]{8})_archive.(?:zip|daf)$/', $this->archiveFileName, $matches)) {
            $expected_package_hash = $matches[1] . '-' . $matches[2];
            if ($this->packageHash != $expected_package_hash) {
                $is_installer_file_valid = false;
                $this->log("[ERROR] Installer and archive mismatch detected.");
            }
        } else {
            $this->log("[ERROR] Invalid archive file name.");
            $is_installer_file_valid = false;
        }
        if (false  === $is_installer_file_valid) {
            $this->errorMessage = "Installer and archive mismatch detected.
                        Ensure uncorrupted installer and matching archive are present.";
            return BootstrapView::VIEW_ERROR;
        }
        if ($this->archiveCheck() == false) {
            return BootstrapView::VIEW_ERROR;
        }
        if (!$this->isManualExtractFound()) {
            if ($this->engineCheck() == false) {
                return BootstrapView::VIEW_ERROR;
            }
            if (!$this->passwordCheck()) {
                return BootstrapView::VIEW_PASSWORD;
            }
        }
        if ($this->installerDirExists()) {
            if (($extract_installer = filter_input(INPUT_GET, 'force-extract-installer', FILTER_VALIDATE_BOOLEAN))) {
                $this->log("Manual extract found with force extract installer get parametr");
            } else {
                $this->log("Manual extract found so not going to extract " . $this->targetDupInst . " dir");
            }
        } else {
            $extract_installer = true;
        }
        if (file_exists($this->targetDupInst)) {
            $this->log("EXTRACT " . $this->targetDupInst . " dir");
            $hash_pattern                 = '[a-z0-9][a-z0-9][a-z0-9][a-z0-9][a-z0-9][a-z0-9][a-z0-9]-[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]';
            $file_patterns_with_hash_file = array(
                'dup-archive__' . $hash_pattern . '.txt'        => 'dup-archive__' . $this->packageHash . '.txt',
                'dup-database__' . $hash_pattern . '.sql'       => 'dup-database__' . $this->packageHash . '.sql',
                'dup-installer-data__' . $hash_pattern . '.sql' => 'dup-installer-data__' . $this->packageHash . '.sql',
                'dup-installer-log__' . $hash_pattern . '.txt'  => 'dup-installer-log__' . $this->packageHash . '.txt',
                'dup-scan__' . $hash_pattern . '.json'          => 'dup-scan__' . $this->packageHash . '.json',
                'dup-scanned-dirs__' . $hash_pattern . '.txt'   => 'dup-scanned-dirs__' . $this->packageHash . '.txt',
                'dup-scanned-files__' . $hash_pattern . '.txt'  => 'dup-scanned-files__' . $this->packageHash . '.txt',
            );
            foreach ($file_patterns_with_hash_file as $file_pattern => $hash_file) {
                $globs = glob($this->targetDupInst . '/' . $file_pattern);
                if (!empty($globs)) {
                    foreach ($globs as $glob) {
                        $file = basename($glob);
                        if ($file != $hash_file) {
                            if (unlink($glob)) {
                                $this->log('Successfully deleted the file ' . $glob);
                            } else {
                                $this->errorMessage .= '[ERROR] Error deleting the file ' . $glob . ' Please manually delete it and try again.';
                                $this->log($this->errorMessage);
                            }
                        }
                    }
                }
            }
        }
        if ($extract_installer) {
            try {
                $this->extractInstaller();
            } catch (Exception $e) {
                $this->log("Extraction exception msg: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                return BootstrapView::VIEW_ERROR;
            }
        } else {
            $this->log("NOTICE: Didn't need to extract the installer.");
        }
        if ($this->isCustomDupFolder && file_exists($this->extractionTmpFolder)) {
            rmdir($this->extractionTmpFolder);
        }
        $config_files              = glob($this->targetDupInst . '/dup-archive__*.txt');
        $config_file_absolute_path = array_pop($config_files);
        if (!file_exists($config_file_absolute_path)) {
            $this->errorMessage = '<b>Archive config file not found in ' . $this->targetDupInst . ' folder.</b> <br><br>';
            return BootstrapView::VIEW_ERROR;
        }
        if (!file_exists($this->targetDupInst)) {
            $this->errorMessage = 'Can\'t extract installer directory. ' .
                'See <a target="_blank" href="https://snapcreek.com/duplicator/docs/faqs-tech/#faq-installer-022-q">this FAQ item</a>' .
                ' for details on how to resolve.</a>';
                return BootstrapView::VIEW_ERROR;
        }
        $bootloader_name = basename(__FILE__);
        $this->archive    = $archive_filepath;
        $this->bootloader = $bootloader_name;
        $this->fixInstallerPerms();
        $this->setCsrfData();
        $this->log("DONE: No detected errors so redirecting to the main installer. Main Installer URI = " . $this->getMainInstallerUrl());
        return BootstrapView::VIEW_REDIRECT;
    }
    public function getMainInstallerUrl()
    {
        $uri_start = BootstrapUtils::getCurrentUrl(false, false, 1);
        if (self::isBridgeInstall()) {
            return $uri_start;
        } else {
            return $uri_start . '/' . $this->targetDupInstFolder . '/main.installer.php';
        }
    }
    public function getMainInstallerUrlData()
    {
        $data = [
            'ctrl_action'     => 'ctrl-step1',
            'ctrl_csrf_token' => SecureCsrf::generate('ctrl-step1'),
            'step_action'     => 'init'
        ];
        if (self::isBridgeInstall()) {
            $data['dup_mu_action']  = 'installer';
            $data['inst_main_path'] = __DIR__ . '/' . $this->targetDupInstFolder . '/main.installer.php';
            $data['brchk']          = BootstrapUtils::getBridgeHash();
        }
        return $data;
    }
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }
    private function installerDirExists()
    {
        return file_exists($this->targetDupInst) &&
            file_exists($this->targetDupInst . "/main.installer.php") &&
            file_exists($this->targetDupInst . "/dup-archive__" . $this->packageHash . ".txt");
    }
    private function isManualExtractFound()
    {
        return $this->installerDirExists() &&
            file_exists($this->targetDupInst . "/" . $this->manualExtractFileName);
    }
    public function appendErrorMessage($message)
    {
        $this->errorMessage .= (strlen($message) ? " \n" : '') . $message;
    }
    public function isZip()
    {
        return (strcasecmp(pathinfo($this->archiveFileName, PATHINFO_EXTENSION), 'zip') === 0);
    }
    protected function isArchiveEncrypted()
    {
        static $isEncrypted = null;
        if ($isEncrypted === null) {
            if ($this->isZip()) {
                if (BootstrapUtils::isZipAvailable()) {
                    $isEncrypted = BootstrapUtils::isZipArchiveEncrypted($this->getArchiveFilePath(), 'main.installer.php');
                } else {
                    $isEncrypted = null;
                    throw new Exception("ERROR: Can't check if zip archive is encrypted. " .
                        "<a target='_blank' href='https://snapcreek.com/duplicator/docs/faqs-tech/#faq-trouble-060-q'>ZipArchive</a> " .
                        "and <a target='_blank' href='http://php.net/manual/en/function.shell-exec.php'>ShellExec unzip</a>" .
                        "are not enabled on this server. Please " .
                        "talk to your host or server admin about enabling at least one of them.<br>" .
                        "Alternative is to manually extract archive then choose Advanced > Manual Extract in installer.");
                }
            } else {
                $isEncrypted = DupArchive::isEncrypted($this->getArchiveFilePath());
            }
        }
        return $isEncrypted;
    }
    protected function passwordCheck()
    {
        if (!$this->isArchiveEncrypted()) {
            return true;
        }
        $this->log('ARCHIVE ENCRYPTED, PASSWORD CHECK');
        $result       = false;
        $password     = (isset($_REQUEST[self::NAME_PWD]) ? BootstrapUtils::sanitizeNSCharsNewline($_REQUEST[self::NAME_PWD]) : '');
        $passwordSend = (strlen($password) > 0 || (isset($_REQUEST[self::NAME_PWD_BUTTON]) && $_REQUEST[self::NAME_PWD_BUTTON] === 'secure'));
        if ($this->isZip()) {
            if (
                $result = BootstrapUtils::zipArchivePasswordCheck(
                    $this->getArchiveFilePath(),
                    $password,
                    'dup-installer/main.installer.php',
                    $this->getZipMode()
                )
            ) {
                $this->log('ZIP ARCHIVE PASSWORD OK ');
            } else {
                $this->log('ZIP ARCHIVE PASSWORD FAIL ');
            }
        } else {
            if ($result = DupArchive::checkPassword($this->getArchiveFilePath(), $password)) {
                $this->log('DUP ARCHIVE PASSWORD OK ');
            } else {
                $this->log('DUP ARCHIVE PASSWORD FAIL ');
            }
        }
        if ($result) {
            $this->archivePwd = $password;
        } else {
            if ($passwordSend) {
                $this->appendErrorMessage('<span class="password-invalid-msg" >Invalid password<span>');
            }
            sleep(1);
        }
        return $result;
    }
    protected function archiveCheck()
    {
        $archiveFilePath     = $this->getArchiveFilePath();
        $archiveExpectedEasy = BootstrapUtils::readableByteSize($this->archiveExpectedSize);
        $archiveActualEasy   = BootstrapUtils::readableByteSize($this->archiveActualSize);
        $archiveFileExists   = file_exists($archiveFilePath);
        if ($this->isManualExtractFound() && !$archiveFileExists) {
            $this->log("[ERROR] Archive file not found!");
            $this->errorMessage = "<style>.diff-list font { font-weight: bold; }</style>"
                . "<b>Archive not found!</b> The archive file cannot be found at the current path:<br/>"
                . "<span class='file-info'>{$archiveFilePath}</span><br/>"
                . "Please make sure the archive remains until the installation process is completed.";
            return false;
        } elseif (!$archiveFileExists) {
            $this->log("[ERROR] Archive file not found!");
            $this->errorMessage = "<style>.diff-list font { font-weight: bold; }</style>"
                . "<b>Archive not found!</b> The required archive file must be present in the <i>'Extraction Path'</i> below. "
                . "When the archive file name was created "
                . "it was given a secure hashed file name.  This file name must be the <i>exact same</i> "
                . "name as when it was created character for character. "
                . "Each archive file has a unique installer associated with it and must be used together.  See the list below for more options:<br/>"
                . "<ul>"
                . "<li>If the archive is not finished downloading please wait for it to complete.</li>"
                . "<li>Rename the file to it original hash name.  See WordPress-Admin ❯ Packages ❯  Details. </li>"
                . "<li>When downloading, both files both should be from the same package line. </li>"
                . "<li>Also see: <a href='https://snapcreek.com/duplicator/docs/faqs-tech/#faq-installer-050-q' target='_blank'>"
                . "How to fix various errors that show up before step-1 of the installer?</a></li>"
                . "</ul><br/>"
                . "<b>Extraction Path:</b> <span class='file-info'>{$this->targetRoot}/</span><br/>";
            return false;
        }
        if (strlen($this->archiveFileSize) > 0 && !self::checkInputValidInt($this->archiveFileSize)) {
            $noOfBits           = PHP_INT_SIZE * 8;
            $this->errorMessage = 'Current is a ' . $noOfBits . '-bit SO. This archive is too large for ' . $noOfBits . '-bit PHP.' . '<br>';
            $this->log('[ERROR] ' . $this->errorMessage);
            $this->errorMessage .= 'Possibibles solutions:<br>';
            $this->errorMessage .= '- Use the file filters to get your package lower to support this server or try the package on a Linux server.' . '<br>';
            $this->errorMessage .= '- Perform a <a target="_blank" href="https://snapcreek.com/duplicator/docs/faqs-tech/#faq-installer-015-q">' .
                'Manual Extract Install</a>' . '<br>';
            switch ($noOfBits == 32) {
                case 32:
                    $this->errorMessage .= '- Ask your host to upgrade the server to 64-bit PHP or install on another system has 64-bit PHP' . '<br>';
                    break;
                case 64:
                    $this->errorMessage .= '- Ask your host to upgrade the server to 128-bit PHP or install on another system has 128-bit PHP' . '<br>';
                    break;
            }
            if (self::isWindows()) {
                $this->errorMessage .= '- <a target="_blank" href="https://snapcreek.com/duplicator/docs/faqs-tech/#faq-trouble-052-q">' .
                    'Windows DupArchive extractor</a> to extract all files from the archive.' . '<br>';
            }
            return false;
        }
        if (($this->archiveRatio < 90) && ($this->archiveActualSize > 0) && ($this->archiveExpectedSize > 0)) {
            $this->log(
                "ERROR: The expected archive size should be around [{$archiveExpectedEasy}]. " .
                "The actual size is currently [{$archiveActualEasy}]."
            );
            $this->log("ERROR: The archive file may not have fully been downloaded to the server");
            $this->errorMessage = "<b>Archive file size warning.</b><br/> The expected archive size is <b class='pass'>[{$archiveExpectedEasy}]</b>. "
                . "Currently the archive size is <b class='fail'>[{$archiveActualEasy}]</b>. <br/>"
                . "The archive file may have <b>not fully been uploaded to the server.</b>"
                . "<ul>"
                . "<li>Download the whole archive from the source website (open WP Admin &gt; Duplicator Pro &gt; Packages) "
                . "and validate that the file size is close to the expected size. </li>"
                . "<li>Make sure to upload the whole archive file to the destination server.</li>"
                . "<li>If the archive file is still uploading then please refresh this page to get an update on the currently uploaded file size.</li>"
                . "</ul>";
            return false;
        }
        return true;
    }
    protected function engineCheck()
    {
        try {
            if ($this->isZip()) {
                if (!BootstrapUtils::isZipAvailable()) {
                    $msg = "ZipArchive and Shell Exec are not enabled on this server. Please " .
                    "talk to your host or server admin about enabling " .
                    "<a target='_blank' href='https://snapcreek.com/duplicator/docs/faqs-tech/#faq-trouble-060-q'>ZipArchive</a> " .
                    "or <a target='_blank' href='http://php.net/manual/en/function.shell-exec.php'>Shell Exec</a> " .
                    "on this server or manually extract archive then choose Advanced > Manual Extract in installer.";
                    throw new Exception($msg);
                }
                if (
                    $this->isArchiveEncrypted() &&
                    BootstrapUtils::isPhpZipAvaiable() &&
                    !BootstrapUtils::isShellZipAvailable() &&
                    version_compare(BootstrapUtils::getLibzipVersion(), '1.2.0', '<')
                ) {
                    ob_start();
                    ?>
                    <b>ZipArchive Error</b><br/>
                    This server is unable to decrypt the archive file (ZipArchive), Libzip version 1.2.0+ is required.<br/>
                    Current Libzip version: <?php echo BootstrapUtils::getLibzipVersion(); ?>
                    Please contact your host or server admin to update Libzip version.
                    <?php
                    $msg = ob_get_clean();
                    throw new Exception($msg);
                }
            } else {
                if ($this->isArchiveEncrypted() && !DupArchive::isEncryptionAvaliable()) {
                    ob_start();
                    ?>
                    <b>DupArchive Error</b><br/>
                    This server is unable to decrypt the archive file (DupArchive) without the PHP OpenSSL module.<br/>
                    Please contact your host or server admin to enable the
                    <a href="https://www.php.net/manual/en/book.openssl.php" target="_blank" >OpenSSL module</a>.
                    <?php
                    $msg = ob_get_clean();
                    throw new Exception($msg);
                }
            }
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();
            return false;
        }
        return true;
    }
    protected function extractInstaller()
    {
        $this->log("Ready to extract the installer");
        $archive_filepath = $this->getArchiveFilePath();
        $this->log("Checking permission of destination folder");
        $destination = $this->targetRoot;
        if (!is_writable($destination)) {
            $this->log("destination folder for extraction is not writable");
            if (BootstrapUtils::chmod($destination, 'u+rwx')) {
                $this->log("Permission of destination folder changed to u+rwx");
            } else {
                $this->log("[ERROR] Permission of destination folder failed to change to u+rwx");
            }
        }
        if (!is_writable($destination)) {
            $this->log("WARNING: The {$destination} directory is not writable.");
            $this->errorMessage  = "NOTICE: The {$destination} directory is not writable on this server please talk to your host or server admin about making ";
            $this->errorMessage .= "<a target='_blank' href='https://snapcreek.com/duplicator/docs/faqs-tech/#faq-trouble-055-q'>" .
            "writable {$destination} directory</a> on this server. <br/>";
            throw new Exception('Destination folter isn\'t writeable');
        }
        if ($this->isZip()) {
            if ($this->extractInstallerZip() == false) {
                throw new Exception('Fail zip extraction');
            }
        } else {
            try {
                DupArchiveExpandBasicEngine::setCallbacks(
                    array($this, 'log'),
                    array($this, 'chmod'),
                    array($this, 'mkdir')
                );
                $offset = DupArchiveExpandBasicEngine::getExtraOffset($archive_filepath, $this->archivePwd);
                $this->log('Expand directory from offset ' . $offset);
                DupArchiveExpandBasicEngine::expandDirectory(
                    $archive_filepath,
                    $this->origDupInstFolder,
                    $this->extractionTmpFolder,
                    $this->archivePwd,
                    false,
                    $offset
                );
                @unlink($this->extractionTmpFolder . "/" . $this->origDupInstFolder . "/" . $this->manualExtractFileName);
            } catch (Exception $ex) {
                $this->log("[ERROR] Error expanding installer subdirectory:" . $ex->getMessage());
                throw $ex;
            }
        }
        if ($this->isCustomDupFolder) {
            $this->log("Move dup-installer folder to custom folder:" .  $this->targetDupInst);
            if (file_exists($this->targetDupInst)) {
                $this->log('Custom folder already exists so delete it');
                if (BootstrapUtils::rrmdir($this->targetDupInst) == false) {
                    throw new Exception('Can\'t remove custom target folder');
                }
            }
            if (rename($this->extractionTmpFolder . '/' . $this->origDupInstFolder, $this->targetDupInst) === false) {
                throw new Exception('Can\'t rename the tmp dup-installer folder');
            }
        }
        $htaccessToRemove = $this->targetDupInst . '/.htaccess';
        if (is_file($htaccessToRemove) && is_writable($htaccessToRemove)) {
            $this->log("Remove Htaccess in dup-installer folder");
            @unlink($htaccessToRemove);
        }
        $is_apache = (strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false || strpos($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') !== false);
        $is_nginx  = (strpos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false);
        $sapi_type                   = php_sapi_name();
        $php_ini_data                = array(
            'max_execution_time'     => 3600,
            'max_input_time'         => -1,
            'ignore_user_abort'      => 'On',
            'post_max_size'          => '4096M',
            'upload_max_filesize'    => '4096M',
            'memory_limit'           => DUPLICATOR_PRO_PHP_MAX_MEMORY,
            'default_socket_timeout' => 3600,
            'pcre.backtrack_limit'   => 99999999999,
        );
        $sapi_type_first_three_chars = substr($sapi_type, 0, 3);
        if ('fpm' === $sapi_type_first_three_chars) {
            $this->log("SAPI: FPM");
            if ($is_apache) {
                $this->log('Server: Apache');
            } elseif ($is_nginx) {
                $this->log('Server: Nginx');
            }
            if (($is_apache && function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules())) || $is_nginx) {
                $htaccess_data = array();
                foreach ($php_ini_data as $php_ini_key => $php_ini_val) {
                    if ($is_apache) {
                        $htaccess_data[] = 'SetEnv PHP_VALUE "' . $php_ini_key . ' = ' . $php_ini_val . '"';
                    } elseif ($is_nginx) {
                        if ('On' == $php_ini_val || 'Off' == $php_ini_val) {
                            $htaccess_data[] = 'php_flag ' . $php_ini_key . ' ' . $php_ini_val;
                        } else {
                            $htaccess_data[] = 'php_value ' . $php_ini_key . ' ' . $php_ini_val;
                        }
                    }
                }
                $htaccess_text      = implode("\n", $htaccess_data);
                $htaccess_file_path = $this->targetDupInst . '/.htaccess';
                $this->log("creating {$htaccess_file_path} with the content:");
                $this->log($htaccess_text);
                @file_put_contents($htaccess_file_path, $htaccess_text);
            }
        } elseif ('cgi' === $sapi_type_first_three_chars || 'litespeed' === $sapi_type) {
            if ('cgi' === $sapi_type_first_three_chars) {
                $this->log("SAPI: CGI");
            } else {
                $this->log("SAPI: litespeed");
            }
            if (version_compare(phpversion(), 5.5) >= 0 && (!$is_apache || 'litespeed' === $sapi_type)) {
                $ini_data = array();
                foreach ($php_ini_data as $php_ini_key => $php_ini_val) {
                    $ini_data[] = $php_ini_key . ' = ' . $php_ini_val;
                }
                $ini_text      = implode("\n", $ini_data);
                $ini_file_path = $this->targetDupInst . '/.user.ini';
                $this->log("creating {$ini_file_path} with the content:");
                $this->log($ini_text);
                @file_put_contents($ini_file_path, $ini_text);
            } else {
                $this->log("No need to create " . $this->targetDupInst . "/.htaccess or " . $this->targetDupInst . "/.user.ini");
            }
        } elseif ("apache2handler" === $sapi_type) {
            $this->log("No need to create " . $this->targetDupInst . "/.htaccess or " . $this->targetDupInst . "/.user.ini");
            $this->log("SAPI: apache2handler");
        } else {
            $this->log("No need to create " . $this->targetDupInst . "/.htaccess or " . $this->targetDupInst . "/.user.ini");
            $this->log("ERROR:  SAPI: Unrecognized");
        }
    }
    protected function extractInstallerZip()
    {
        $success = false;
        switch ($this->getZipMode()) {
            case self::ZIP_MODE_ARCHIVE:
                $this->log("ZipArchive exists so using that");
                $success = $this->extractInstallerZipArchive($this->getArchiveFilePath(), $this->origDupInstFolder, $this->extractionTmpFolder);
                if ($success) {
                    $this->log('Successfully extracted with ZipArchive');
                } else {
                    if (0 == $this->installerFilesFound) {
                        $this->errorMessage = "[ERROR] This archive is not properly formatted and does not contain a " . $this->origDupInstFolder .
                        " directory. Please make sure you are attempting to install " .
                        "the original archive and not one that has been reconstructed.";
                        $this->log($this->errorMessage);
                    } else {
                        $this->errorMessage = '[ERROR] Error extracting with ZipArchive. ';
                        $this->log($this->errorMessage);
                    }
                }
                break;
            case self::ZIP_MODE_SHELL:
                $success = $this->extractInstallerShellexec($this->getArchiveFilePath(), $this->origDupInstFolder, $this->extractionTmpFolder);
                $this->log("Resetting perms of items in folder {$this->targetDupInst}");
                self::setPermsToDefaultR($this->targetDupInst);
                if ($success) {
                    $this->log('Successfully extracted with Shell Exec');
                    $this->errorMessage = '';
                } else {
                    $this->errorMessage .= '[ERROR] Error extracting with Shell Exec. ' .
                    'Please manually extract archive then choose Advanced > Manual Extract in installer.';
                    $this->log($this->errorMessage);
                }
                break;
            case self::ZIP_MODE_NONE:
                if (!BootstrapUtils::isZipAvailable()) {
                    $this->log("WARNING: ZipArchive and Shell Exec are not enabled on this server.");
                    $this->errorMessage = "NOTICE: ZipArchive and Shell Exec are not enabled on this server. Please " .
                    "talk to your host or server admin about enabling " .
                    "<a target='_blank' href='https://snapcreek.com/duplicator/docs/faqs-tech/#faq-trouble-060-q'>ZipArchive</a> " .
                    "or <a target='_blank' href='http://php.net/manual/en/function.shell-exec.php'>Shell Exec</a> " .
                    "on this server or manually extract archive then choose Advanced > Manual Extract in installer.";
                }
                break;
        }
        return $success;
    }
    protected function setCsrfData()
    {
        SecureCsrf::setKeyVal('installerOrigCall', BootstrapUtils::getCurrentUrl());
        SecureCsrf::setKeyVal('installerOrigPath', __FILE__);
        SecureCsrf::setKeyVal('archive', $this->archive);
        SecureCsrf::setKeyVal('bootloader', $this->bootloader);
        SecureCsrf::setKeyVal(self::CSRF_KEY_ARCHIVE_PASSWORD, $this->archivePwd);
        SecureCsrf::setKeyVal('booturl', '//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        SecureCsrf::setKeyVal('bootLogFile', $this->getBootLogFilePath());
        SecureCsrf::setKeyVal('package_hash', $this->packageHash);
        SecureCsrf::setKeyVal('secondaryHash', $this->secondaryHash);
        if (self::isBridgeInstall()) {
            if (!isset($_REQUEST['inst_main_url'])) {
                throw new Exception('Invalid input data, inst_main_url required');
            }
            SecureCsrf::setKeyVal('originalDupInstallerUrl', $_REQUEST['inst_main_url']);
        } else {
            SecureCsrf::setKeyVal('originalDupInstallerUrl', '');
        }
        SecureCsrf::setKeyVal('isManualExtractFound', $this->isManualExtractFound());
    }
    public function getRedirectForm()
    {
        $mainInstallerURL = $this->getMainInstallerUrl();
        $data             = $this->getMainInstallerUrlData();
        $id               = uniqid();
        ob_start();
        ?>
        <form id="<?php echo $id; ?>" method="get" action="<?php echo htmlspecialchars($mainInstallerURL); ?>" >
            <?php
            foreach ($data as $name => $value) {
                if ('csrf_token' != $name) {
                    $_SESSION[$name] = $value;
                }
                ?>
                <input type="hidden" name="<?php echo htmlspecialchars($name); ?>" value="<?php echo htmlspecialchars($value); ?>" >
                <?php
            }
            ?>
        </form>
        <script>
            window.onload = function() {
                document.getElementById(<?php echo json_encode($id); ?>).submit(); 
            }
        </script>
        <?php
        return ob_get_clean();
    }
    private function fixInstallerPerms()
    {
        $file_perms = 'u+rw';
        $dir_perms  = 'u+rwx';
        $installer_dir_path = $this->targetDupInst;
        $this->setPerms($installer_dir_path, $dir_perms, false);
        $this->setPerms($installer_dir_path, $file_perms, true);
    }
    private function setPerms($directory, $perms, $do_files)
    {
        if (!$do_files) {
            $this->setPermsOnItem($directory, $perms);
        }
        $item_names = array_diff(scandir($directory), array('.', '..'));
        foreach ($item_names as $item_name) {
            $path = "$directory/$item_name";
            if (($do_files && is_file($path)) || (!$do_files && !is_file($path))) {
                $this->setPermsOnItem($path, $perms);
            }
        }
    }
    private function setPermsOnItem($path, $perms)
    {
        if (($result = BootstrapUtils::chmod($path, $perms)) === false) {
            $this->log("ERROR: Couldn't set permissions of $path<br/>");
        } else {
            $this->log("Set permissions of $path<br/>");
        }
        return $result;
    }
    private function compareStrings($oldString, $newString)
    {
        $ret = '';
        for ($i = 0; isset($oldString[$i]) || isset($newString[$i]); $i++) {
            if (!isset($oldString[$i])) {
                $ret .= '<font color="red">' . $newString[$i] . '</font>';
                continue;
            }
            for ($char = 0; isset($oldString[$i][$char]) || isset($newString[$i][$char]); $char++) {
                if (!isset($oldString[$i][$char])) {
                    $ret .= '<font color="red">' . substr($newString[$i], $char) . '</font>';
                    break;
                } elseif (!isset($newString[$i][$char])) {
                    break;
                }
                if (ord($oldString[$i][$char]) != ord($newString[$i][$char])) {
                    $ret .= '<font color="red">' . $newString[$i][$char] . '</font>';
                } else {
                    $ret .= $newString[$i][$char];
                }
            }
        }
        return $ret;
    }
    public function log($s, $deleteOld = false)
    {
        static $logfile = null;
        if (is_null($logfile)) {
            $logfile = $this->getBootLogFilePath();
        }
        if ($deleteOld && file_exists($logfile)) {
            @unlink($logfile);
        }
        $timestamp = date('M j H:i:s');
        return @file_put_contents($logfile, '[' . $timestamp . '] ' . $this->postprocessLog($s) . "\n", FILE_APPEND);
    }
    public function getBootLogFilePath()
    {
        return $this->targetRoot . '/dup-installer-bootlog__' . $this->secondaryHash . '.txt';
    }
    protected function postprocessLog($str)
    {
        return str_replace(array(
        $this->getArchiveFileHash(),
        $this->packageHash,
        $this->secondaryHash
        ), '[HASH]', $str);
    }
    public function getArchiveFileHash()
    {
        static $fileHash = null;
        if (is_null($fileHash)) {
            $fileHash = preg_replace('/^.+_([a-z0-9]+)_[0-9]{14}_archive\.(?:daf|zip)$/', '$1', $this->archiveFileName);
        }
        return $fileHash;
    }
    private function extractInstallerZipArchive($archive_filepath, $origDupInstFolder, $destination, $checkSubFolder = false)
    {
        $success              = true;
        $zipArchive           = new ZipArchive();
        $subFolderArchiveList = array();
        if (($zipOpenRes = $zipArchive->open($archive_filepath)) !== true) {
            $this->log("[ERROR] Couldn't open archive archive file with ZipArchive CODE[" . $zipOpenRes . "]");
            return false;
        }
        if (strlen($this->archivePwd)) {
            $zipArchive->setPassword($this->archivePwd);
        }
        $this->log("Successfully opened archive file.");
        $folder_prefix = $origDupInstFolder . '/';
        $this->log("Extracting all files from archive within " . $origDupInstFolder);
        $this->installerFilesFound = 0;
        for ($i = 0; $i < $zipArchive->numFiles; $i++) {
            $stat = $zipArchive->statIndex($i);
            if ($checkSubFolder == false) {
                $filenameCheck = $stat['name'];
                $filename      = $stat['name'];
                $tmpSubFolder  = null;
            } else {
                $safePath = rtrim(BootstrapUtils::setSafePath($stat['name']), '/');
                $tmpArray = explode('/', $safePath);
                if (count($tmpArray) < 2) {
                    continue;
                }
                $tmpSubFolder = $tmpArray[0];
                array_shift($tmpArray);
                $filenameCheck = implode('/', $tmpArray);
                $filename      = $stat['name'];
            }
            if (!BootstrapUtils::startsWith($filenameCheck, $folder_prefix)) {
                continue;
            }
            $this->installerFilesFound++;
            if (!empty($tmpSubFolder) && !in_array($tmpSubFolder, $subFolderArchiveList)) {
                $subFolderArchiveList[] = $tmpSubFolder;
            }
            if (basename($filename) === $this->manualExtractFileName) {
                $this->log("Skipping manual extract file: {$filename}");
                continue;
            }
            if ($zipArchive->extractTo($destination, $filename) === true) {
                $this->log("Success: {$filename} >>> {$destination}");
            } else {
                $this->log("[ERROR] Error extracting {$filename} >>> {$destination}");
                $success = false;
                break;
            }
        }
        if ($checkSubFolder && count($subFolderArchiveList) !== 1) {
            $this->log("Error: Multiple dup subfolder archive");
            $success = false;
        } else {
            if ($checkSubFolder) {
                $this->moveUpfromSubFolder($destination . '/' . $subFolderArchiveList[0], true);
            }
            $lib_directory     = $destination . '/' . $origDupInstFolder . '/lib';
            $snaplib_directory = $lib_directory . '/snaplib';
            if (!file_exists($snaplib_directory)) {
                $folder_prefix = 'snaplib/';
                $destination   = $lib_directory;
                for ($i = 0; $i < $zipArchive->numFiles; $i++) {
                    $stat     = $zipArchive->statIndex($i);
                    $filename = $stat['name'];
                    if (BootstrapUtils::startsWith($filename, $folder_prefix)) {
                        $this->installerFilesFound++;
                        if ($zipArchive->extractTo($destination, $filename) === true) {
                            $this->log("Success: {$filename} >>> {$destination}");
                        } else {
                            $this->log("[ERROR] Error extracting {$filename} from archive archive file");
                            $success = false;
                            break;
                        }
                    }
                }
            }
        }
        if ($zipArchive->close() === true) {
            $this->log("Successfully closed archive file");
        } else {
            $this->log("[ERROR] Problem closing archive file");
            $success = false;
        }
        if ($success != false && $this->installerFilesFound < 10) {
            if ($checkSubFolder) {
                $this->log("[ERROR] Couldn't find the installer directory in the archive!");
                $success = false;
            } else {
                $this->log("[ERROR] Couldn't find the installer directory in archive root! Check subfolder");
                return $this->extractInstallerZipArchive($archive_filepath, $origDupInstFolder, $destination, true);
            }
        }
        return $success;
    }
    public static function isWindows()
    {
        static $isWindows = null;
        if (is_null($isWindows)) {
            $isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
        }
        return $isWindows;
    }
    public static function setPermsToDefaultR($directory)
    {
        $dir      = new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($dir, \RecursiveIteratorIterator::SELF_FIRST);
        $defaultFilePermission = 0666 & ~umask();
        $defaultDirPermission  = 0777 & ~umask();
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                BootstrapUtils::chmod($item->getPathname(), $defaultFilePermission);
            }
            if ($item->isDir()) {
                BootstrapUtils::chmod($item->getPathname(), $defaultDirPermission);
            }
        }
    }
    public static function checkInputValidInt($input)
    {
        return (filter_var($input, FILTER_VALIDATE_INT) === 0 || filter_var($input, FILTER_VALIDATE_INT));
    }
    private function moveUpfromSubFolder($subFolderName, $deleteSubFolder = false)
    {
        if (!is_dir($subFolderName)) {
            return false;
        }
        $parentFolder = dirname($subFolderName);
        if (!is_writable($parentFolder)) {
            return false;
        }
        $success = true;
        if (($subList = glob(rtrim($subFolderName, '/') . '/*', GLOB_NOSORT)) === false) {
            $this->log("[ERROR] Problem glob folder " . $subFolderName);
            return false;
        } else {
            foreach ($subList as $cName) {
                $destination = $parentFolder . '/' . basename($cName);
                if (file_exists($destination)) {
                    $success = BootstrapUtils::rrmdir($destination);
                }
                if ($success) {
                    $success = rename($cName, $destination);
                } else {
                    break;
                }
            }
            if ($success && $deleteSubFolder) {
                $success = BootstrapUtils::rrmdir($subFolderName);
            }
        }
        if (!$success) {
            $this->log("[ERROR] Problem om moveUpfromSubFolder subFolder:" . $subFolderName);
        }
        return $success;
    }
    private function extractInstallerShellexec($archive_filepath, $origDupInstFolder, $destination)
    {
        $success = false;
        $this->log("Attempting to use Shell Exec");
        $unzip_filepath = BootstrapUtils::getUnzipFilePath();
        if ($unzip_filepath == null) {
            return false;
        }
        $params = "-o -q";
        if (strlen($this->archivePwd)) {
            $params .= ' -P ' . escapeshellarg($this->archivePwd);
        }
        $unzip_command = escapeshellcmd($unzip_filepath) . ' ' . $params . ' ' .
        escapeshellarg($archive_filepath) . ' ' .
        escapeshellarg($origDupInstFolder . '/*') .
        ' -d ' . escapeshellarg($destination) .
        ' -x ' . escapeshellarg($origDupInstFolder . '/' . $this->manualExtractFileName) . ' 2>&1';
        $this->log("Executing $unzip_command");
        $shellOutput = Shell::runCommand($unzip_command, Shell::AVAILABLE_COMMANDS);
        if ($shellOutput !== false && $shellOutput->isEmpty()) {
            $this->log("Shell exec unzip succeeded");
            $success = true;
        } else {
            $this->log("[ERROR] Shell exec unzip failed. Output={$shellOutput->getOutputAsString()}");
        }
        return $success;
    }
    private function getArchiveFilePath()
    {
        if (($archive_filepath = filter_input(INPUT_GET, 'archive', FILTER_SANITIZE_SPECIAL_CHARS)) != false) {
            if (is_dir($archive_filepath) && file_exists($archive_filepath . '/' . $this->archiveFileName)) {
                $archive_filepath = $archive_filepath . '/' . $this->archiveFileName;
            } else {
                $archive_filepath = $archive_filepath;
            }
        } else {
            $archive_filepath = $this->targetRoot . '/' . $this->archiveFileName;
        }
        if (($realPath = realpath($archive_filepath)) !== false) {
            return $realPath;
        } else {
            return $archive_filepath;
        }
    }
    private function getZipMode()
    {
        $zip_mode = self::ZIP_MODE_AUTO;
        if (isset($_GET['zipmode'])) {
            $zipmode_string = $_GET['zipmode'];
            $this->log("Unzip mode specified in querystring: $zipmode_string");
            switch ($zipmode_string) {
                case 'autounzip':
                    $zip_mode = self::ZIP_MODE_AUTO;
                    break;
                case 'ziparchive':
                    $zip_mode = self::ZIP_MODE_ARCHIVE;
                    break;
                case 'shellexec':
                    $zip_mode = self::ZIP_MODE_SHELL;
                    break;
            }
        }
        switch ($zip_mode) {
            case self::ZIP_MODE_AUTO:
            case self::ZIP_MODE_ARCHIVE:
                if (
                    BootstrapUtils::isPhpZipAvaiable() && (
                        !$this->isArchiveEncrypted() ||
                        !version_compare(BootstrapUtils::getLibzipVersion(), '1.2.0', '<')
                    )
                ) {
                    return self::ZIP_MODE_ARCHIVE;
                } elseif (Shell::test()) {
                    return self::ZIP_MODE_SHELL;
                } else {
                    return self::ZIP_MODE_NONE;
                }
                break;
            case self::ZIP_MODE_SHELL:
                if (Shell::test()) {
                    return self::ZIP_MODE_SHELL;
                } elseif (
                    BootstrapUtils::isPhpZipAvaiable() && (
                        !$this->isArchiveEncrypted() ||
                        !version_compare(BootstrapUtils::getLibzipVersion(), '1.2.0', '<')
                    )
                ) {
                    return self::ZIP_MODE_ARCHIVE;
                } else {
                    return self::ZIP_MODE_NONE;
                }
                break;
            default:
                return self::ZIP_MODE_NONE;
        }
        return $zip_mode;
    }
    public function getFilesWithExtension($extension)
    {
        $files = array();
        foreach (glob("*.{$extension}") as $name) {
            if (file_exists($name)) {
                $files[] = $name;
            }
        }
        if (count($files) > 0) {
            return $files;
        }
        if (($dh = opendir($this->targetRoot))) {
            while (false !== ($name = readdir($dh))) {
                $ext = substr($name, strrpos($name, '.') + 1);
                if (in_array($ext, array($extension))) {
                    $files[] = $name;
                }
            }
            closedir($dh);
        }
        return $files;
    }
    public function getVersion()
    {
        return $this->version;
    }
    public function getSecondaryHash()
    {
        return $this->secondaryHash;
    }
    public static function isBridgeInstall()
    {
        return defined('DUPLICATOR_MU_PLUGIN_VERSION');
    }
}
}
namespace Duplicator\Libs\Shell {
use Exception;
class Shell
{
    const AVAILABLE_COMMANDS = Shell::CMD_EXEC | Shell::CMD_POPEN | Shell::CMD_SHELL_EXEC;
    const CMD_SHELL_EXEC = 0b100;
    const CMD_POPEN      = 0b010;
    const CMD_EXEC       = 0b001;
    public static function runCommand($command, $useCmd = self::CMD_POPEN | self::CMD_EXEC)
    {
        $shellFunction = self::getShellFunction($useCmd);
        $output        = null;
        $code          = null;
        $isCodeAvailable = (($useCmd & self::CMD_SHELL_EXEC) == 0);
        switch ($shellFunction) {
            case self::CMD_POPEN:
                if (($handler = popen($command, 'r')) === false) {
                    return false;
                }
                while (!feof($handler)) {
                    $output[] = fgets($handler);
                }
                $code = pclose($handler);
                break;
            case self::CMD_EXEC:
                if ((exec($command, $output, $code)) === false) {
                    return false;
                }
                break;
            case self::CMD_SHELL_EXEC:
                if (($output = shell_exec($command)) === false) {
                    return false;
                }
                $output = (string) $output;
                $code   = 0;
                break;
            default:
                return false;
        }
        return new ShellOutput($output, $code, $shellFunction, $isCodeAvailable);
    }
    private static function getAvaliableCmdFuncs()
    {
        static $availableFunctions = null;
        if (is_null($availableFunctions)) {
            if (self::hasDisabledFunctions(['escapeshellarg', 'escapeshellcmd', 'extension_loaded'])) {
                $availableFunctions = 0;
                return $availableFunctions;
            }
            if (!self::hasDisabledFunctions(['popen', 'pclose'])) {
                $availableFunctions = ($availableFunctions | self::CMD_POPEN);
            }
            if (!self::hasDisabledFunctions('exec')) {
                $availableFunctions = ($availableFunctions | self::CMD_EXEC);
            }
            if (!self::hasDisabledFunctions('shell_exec')) {
                $availableFunctions = ($availableFunctions | self::CMD_SHELL_EXEC);
            }
        }
        return $availableFunctions;
    }
    private static function getShellFunction($useCmd)
    {
        if (self::CMD_SHELL_EXEC & $useCmd & self::getAvaliableCmdFuncs()) {
            return self::CMD_SHELL_EXEC;
        } elseif (self::CMD_POPEN & $useCmd & self::getAvaliableCmdFuncs()) {
            return self::CMD_POPEN;
        } elseif (self::CMD_EXEC & $useCmd & self::getAvaliableCmdFuncs()) {
            return self::CMD_EXEC;
        } else {
            return false;
        }
    }
    public static function hasDisabledFunctions($functions)
    {
        if (is_scalar($functions)) {
            $functions = [$functions];
        }
        if (array_intersect($functions, self::getDisalbedFunctions())) {
            return true;
        }
        foreach ($functions as $function) {
            if (!function_exists($function)) {
                return true;
            }
        }
        return false;
    }
    protected static function getDisalbedFunctions()
    {
        static $funcsList = null;
        if (is_null($funcsList)) {
            $funcsList = [];
            if (function_exists('ini_get')) {
                if (($ini = ini_get('disable_functions')) === false) {
                    $ini = '';
                }
                $funcsList = array_map('trim', explode(',', $ini));
                if (self::isSuhosinEnabled()) {
                    if (($ini = ini_get("suhosin.executor.func.blacklist")) === false) {
                        $ini = '';
                    }
                    $funcsList = array_merge($funcsList, array_map('trim', explode(',', $ini)));
                    $funcsList = array_values(array_unique($funcsList));
                }
            }
        }
        return $funcsList;
    }
    public static function test($useCmd = self::CMD_POPEN | self::CMD_EXEC)
    {
        static $testsCache = [];
        if (!isset($testsCache[$useCmd])) {
            $result = false;
            if (self::getShellFunction($useCmd) === 0) {
                $result = false;
            } else {
                if (($shellOutput = Shell::runCommand('echo test', $useCmd)) === false) {
                    $result = false;
                } else {
                    $result = (trim($shellOutput->getOutputAsString()) === 'test');
                }
            }
            $testsCache[$useCmd] = $result;
        }
        return $testsCache[$useCmd];
    }
    public static function escapeshellargWindowsSupport($string)
    {
        if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
            if (strstr($string, '%') || strstr($string, '!')) {
                $result = '"' . str_replace('"', '', $string) . '"';
                return $result;
            }
        }
        return escapeshellarg($string);
    }
    public static function getCompressionParam($isCompressed)
    {
        if ($isCompressed) {
            $parameter = '-6';
        } else {
            $parameter = '-0';
        }
        return $parameter;
    }
    public static function isSuhosinEnabled()
    {
        return extension_loaded('suhosin');
    }
}
}
namespace Duplicator\Libs\Shell {
use Exception;
class ShellOutput
{
    private $method = '';
    private $output = [];
    private $code = -1;
    private $isCodeAvaiable = true;
    public function __construct($output, $code, $method, $isCodeAvaiable = true)
    {
        if (is_scalar($output) || is_null($output)) {
            $output = (string) $output;
            if (strlen($output) == 0) {
                $output = [];
            } elseif (($output = preg_split("/(\r\n|\n|\r)/", (string) $output)) === false) {
                $output = [];
            }
        }
        if ($method == 0) {
            throw new Exception('Invalid method');
        }
        $this->output         = self::formatOutput($output);
        $this->code           = (int) $code;
        $this->method         = $method;
        $this->isCodeAvaiable = (bool) $isCodeAvaiable;
    }
    private static function formatOutput($output)
    {
        foreach ($output as $key => $line) {
            $line = preg_replace('~\r\n?~', "\n", $line);
            if (strlen($line) == 0 || substr($line, -1) !== "\n") {
                $line .= "\n";
            }
            $output[$key] = $line;
        }
        return $output;
    }
    public function getOutputAsString()
    {
        return implode('', $this->output);
    }
    public function getArrayWithAllOutputLines()
    {
        return $this->output;
    }
    public function getCode()
    {
        if (!$this->isCodeAvaiable) {
            throw new Exception('The shell command return code is not available.');
        }
        return $this->code;
    }
    public function getOutputMethod()
    {
        return $this->method;
    }
    public function getOutputMethodName()
    {
        switch ($this->method) {
            case Shell::CMD_POPEN:
                return 'popen';
            case Shell::CMD_EXEC:
                return 'exec';
            case Shell::CMD_SHELL_EXEC:
                return 'shell_exec';
            default:
                return 'unknown';
        }
    }
    public function isEmpty()
    {
        return (strlen(trim($this->getOutputAsString())) == 0);
    }
}
}
namespace Duplicator\Installer\Bootstrap {
use Duplicator\Installer\Bootstrap\BootstrapRunner;
use Duplicator\Libs\Shell\Shell;
use Exception;
use ZipArchive;
class BootstrapUtils
{
    public static function isIniValChangeable($setting)
    {
        static $ini_all;
        if (!isset($ini_all)) {
            $ini_all = false;
            if (function_exists('ini_get_all')) {
                $ini_all = ini_get_all();
            }
        }
        if (isset($ini_all[$setting]['access']) && ( INI_ALL === ( $ini_all[$setting]['access'] & 7 ) || INI_USER === ( $ini_all[$setting]['access'] & 7 ) )) {
            return true;
        }
        if (!is_array($ini_all)) {
            return true;
        }
        return false;
    }
    public static function phpVersionCheck($minPhpVer)
    {
        if (version_compare(PHP_VERSION, $minPhpVer, '>=')) {
            return true;
        }
        $match = null;
        if (preg_match("#^\d+(\.\d+)*#", PHP_VERSION, $match)) {
            $phpVersion = $match[0];
        } else {
            $phpVersion = PHP_VERSION;
        }
        ?><!DOCTYPE html>
            <html>
                <head>
                    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                    <meta name="robots" content="noindex,nofollow">
                    <title>Duplicator Professional - issue</title>
                </head>
                <body>
                    <div>
                        <h1>DUPLICATOR PRO ISSUE: PHP <?php echo $minPhpVer; ?> REQUIRED</h1>
                        <p>
                            This server is running PHP: <b><?php echo $phpVersion; ?></b>. <i>A minimum of <b>PHP 
                        <?php echo $minPhpVer; ?></b> is required</i>.<br><br>
                            <b>Contact your hosting provider or server administrator and let them know you would like to upgrade your PHP version.</b>
                        </p>
                    </div>
                </body>
            </html>
            <?php
            die();
    }
    public static function getLibzipVersion()
    {
        static $zlibVersion =  null;
        if (is_null($zlibVersion)) {
            ob_start();
            if (function_exists('phpinfo')) {
                phpinfo(INFO_MODULES);
            }
            $info = ob_get_clean();
            if (preg_match('/<td\s.*?>\s*(libzip.*\sver.+?)\s*<\/td>\s*<td\s.*?>\s*(.+?)\s*<\/td>/i', $info, $matches) !== 1) {
                $zlibVersion = "0";
            } else {
                $zlibVersion = $matches[2];
            }
        }
        return $zlibVersion;
    }
    public static function isZipAvailable()
    {
        return (self::isPhpZipAvaiable() || self::isShellZipAvailable());
    }
    public static function isPhpZipAvaiable()
    {
        return self::classExists(ZipArchive::class);
    }
    public static function isShellZipAvailable()
    {
        return (self::getUnzipFilePath() !== false);
    }
    public static function isZipArchiveEncrypted($path, $fileToCheck)
    {
        if (self::isPhpZipAvaiable()) {
            $zip = new ZipArchive();
            if (($zipOpenRes = $zip->open($path)) !== true) {
                $message = "[ERROR] Couldn't open archive archive file with ZipArchive CODE[" . $zipOpenRes . "]";
                throw new Exception($message);
            }
            if (($stats = $zip->statName($fileToCheck, ZipArchive::FL_NODIR))  == false) {
                throw new Exception('Formatting archive error, cannot find file ' . $fileToCheck);
            }
            if (isset($stats['encryption_method'])) {
                $isEncrypt = ($stats['encryption_method'] > 0);
            } else {
                $isEncrypt = ($zip->getFromIndex($stats['index']) === false);
            }
            $zip->close();
            return $isEncrypt;
        } elseif (self::isShellZipAvailable()) {
            return self::isZipArchiveEncryptedShellUnzip($path, $fileToCheck);
        } else {
            throw new Exception('Zip archve isn\'t avaliable');
        }
    }
    protected static function isZipArchiveEncryptedShellUnzip($path, $fileToCheck)
    {
        $tempFolderName = "temp_0oA8wkOvxjKtngR_dir";
        $unzipFilepath  = self::getUnzipFilePath();
        $unzipCommand   = escapeshellcmd($unzipFilepath) .
            " -o " . escapeshellcmd($path) . " " .
            escapeshellcmd("dup-installer/$fileToCheck") .
            " -d " . escapeshellcmd(dirname($path)) . "/" . escapeshellcmd($tempFolderName) . "/ 2>&1";
        $output         = Shell::runCommand($unzipCommand, Shell::AVAILABLE_COMMANDS);
        $encrypted      = true;
        if (file_exists(dirname($path) . "/$tempFolderName/dup-installer/$fileToCheck")) {
            $encrypted = false;
        }
        BootstrapUtils::rrmdir(dirname($path) . "/$tempFolderName");
        return $encrypted;
    }
    public static function zipArchivePasswordCheck($archivePath, $password, $fileToCheck, $zipMode)
    {
        if ($zipMode == BootstrapRunner::ZIP_MODE_NONE) {
            throw new Exception("NOTICE: ZipArchive and Shell Exec are not enabled on this server. Please " .
                "talk to your host or server admin about enabling " .
                "<a target='_blank' href='https://snapcreek.com/duplicator/docs/faqs-tech/#faq-trouble-060-q'>ZipArchive</a> " .
                "or <a target='_blank' href='http://php.net/manual/en/function.shell-exec.php'>Shell Exec</a> " .
                "on this server or manually extract archive then choose Advanced > Manual Extract in installer.");
        }
        if ($zipMode == BootstrapRunner::ZIP_MODE_ARCHIVE) {
            $zip = new ZipArchive();
            if (($zipOpenRes = $zip->open($archivePath)) !== true) {
                $message = "[ERROR] Couldn't open archive archive file with ZipArchive CODE[" . $zipOpenRes . "]";
                throw new Exception($message);
            }
            if (($stats = $zip->statName(basename($fileToCheck), ZipArchive::FL_NODIR))  == false) {
                throw new Exception("Formatting archive error, cannot find the file " . basename($fileToCheck));
            }
            $zip->setPassword($password);
            $result = $zip->getFromIndex($stats['index']);
            $zip->close();
            return $result;
        }
        if ($zipMode == BootstrapRunner::ZIP_MODE_SHELL) {
            if ($password == "") {
                return false;
            }
            $destinationDir = dirname($archivePath) . "/tmp";
            $unzip_filepath = self::getUnzipFilePath();
            if ($unzip_filepath == null) {
                throw new Exception("Could not find unzip app, and ZIP_MODE_SHELL is chosen.");
            }
            $params        = "-o -P " . escapeshellarg($password);
            $unzip_command = escapeshellcmd($unzip_filepath) . ' ' . $params . ' ' .
            escapeshellarg($archivePath) . ' ' .
            escapeshellarg($fileToCheck) .
            ' -d ' . escapeshellarg($destinationDir) . ' 2>&1';
            $shellOutput   = Shell::runCommand($unzip_command, Shell::AVAILABLE_COMMANDS);
            if ($shellOutput === false) {
                $errorMsg = "[ERROR] Shell exec unzip failed. Shell::runCommand returned false.";
                self::rrmdir($destinationDir);
                throw new Exception($errorMsg);
            }
            if (file_exists($destinationDir . "/" . $fileToCheck)) {
                self::rrmdir($destinationDir);
                return true; // Password is correct
            }
            $shellOutputAsString = $shellOutput->getOutputAsString();
            $matchResult         = preg_match('/skipping:.*incorrect password/', $shellOutputAsString);
            if ($matchResult) {
                self::rrmdir($destinationDir);
                return false; // Incorrect password
            }
            $errorMsg    = "[ERROR] Shell exec unzip failed. Output={$shellOutputAsString}";
            $matchResult = preg_match('/skipping:.*need PK compat./', $shellOutputAsString);
            if ($matchResult) {
                $errorMsg .= "</br>It looks like you haven't used 'shell zip' engine when you created this archive. "
                . "Either create new package and use 'shell zip' as archive engine, or "
                . "contact the hosting manager and ask them to activate the ZipArchive class, then try again.";
            } else {
                $errorMsg .= "</br>If you can't fix the problem with 'shell unzip', contact the hosting manager and "
                . "ask them to activate the ZipArchive class, then try again.";
            }
            self::rrmdir($destinationDir);
            throw new Exception($errorMsg);
        }
        throw new Exception("Unrecognised zipMode = $zipMode passed to function zipArchivePasswordCheck.");
    }
    public static function getCurrentUrl($queryString = true, $requestUri = false, $getParentDirLevel = 0)
    {
        if (isset($_SERVER['HTTP_X_ORIGINAL_HOST'])) {
            $host = $_SERVER['HTTP_X_ORIGINAL_HOST'];
        } else {
            $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME']; //WAS SERVER_NAME and caused problems on some boxes
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $_SERVER ['HTTPS'] = 'on';
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'https') {
            $_SERVER ['HTTPS'] = 'on';
        }
        if (isset($_SERVER['HTTP_CF_VISITOR'])) {
            $visitor = json_decode($_SERVER['HTTP_CF_VISITOR']);
            if (is_object($visitor) && property_exists($visitor, 'scheme') && $visitor->scheme == 'https') {
                $_SERVER ['HTTPS'] = 'on';
            }
        }
        $protocol = 'http' . ((isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on') ? 's' : '');
        if ($requestUri) {
            $serverUrlSelf = preg_replace('/\?.*$/', '', $_SERVER['REQUEST_URI']);
        } else {
            $serverUrlSelf = $_SERVER['SCRIPT_NAME'];
            for ($i = 0; $i < $getParentDirLevel; $i++) {
                $serverUrlSelf = preg_match('/^[\\\\\/]?$/', dirname($serverUrlSelf)) ? '' : dirname($serverUrlSelf);
            }
        }
        $query = ($queryString && isset($_SERVER['QUERY_STRING']) && strlen($_SERVER['QUERY_STRING']) > 0 ) ? '?' . $_SERVER['QUERY_STRING'] : '';
        return $protocol . '://' . $host . $serverUrlSelf . $query;
    }
    public static function chmod($file, $mode)
    {
        if (!file_exists($file)) {
            return false;
        }
        $octalMode = 0;
        if (is_int($mode)) {
            $octalMode = $mode;
        } elseif (is_string($mode)) {
            $mode = trim($mode);
            if (preg_match('/([0-7]{1,3})/', $mode)) {
                $octalMode = intval(('0' . $mode), 8);
            } elseif (preg_match_all('/(a|[ugo]{1,3})([-=+])([rwx]{1,3})/', $mode, $gMatch, PREG_SET_ORDER)) {
                if (!function_exists('fileperms')) {
                    return false;
                }
                $octalMode = (fileperms($file) & 0777);
                foreach ($gMatch as $matches) {
                    $group = $matches[1];
                    if ($group === 'a') {
                        $group = 'ugo';
                    }
                    $action = $matches[2];
                    $gPerms = $matches[3];
                    $octalGroupMode = 0;
                    $subPerm  = 0;
                    $subPerm += strpos($gPerms, 'x') !== false ? 1 : 0; // mask 001
                    $subPerm += strpos($gPerms, 'w') !== false ? 2 : 0; // mask 010
                    $subPerm += strpos($gPerms, 'r') !== false ? 4 : 0; // mask 100
                    $ugoLen = strlen($group);
                    if ($action === '=') {
                        $ugoMaskInvert = 0777;
                        for ($i = 0; $i < $ugoLen; $i++) {
                            switch ($group[$i]) {
                                case 'u':
                                    $octalGroupMode = $octalGroupMode | $subPerm << 6; // mask xxx000000
                                    $ugoMaskInvert  = $ugoMaskInvert & 077;
                                    break;
                                case 'g':
                                    $octalGroupMode = $octalGroupMode | $subPerm << 3; // mask 000xxx000
                                    $ugoMaskInvert  = $ugoMaskInvert & 0707;
                                    break;
                                case 'o':
                                    $octalGroupMode = $octalGroupMode | $subPerm; // mask 000000xxx
                                    $ugoMaskInvert  = $ugoMaskInvert & 0770;
                                    break;
                            }
                        }
                        $octalMode = $octalMode & ($ugoMaskInvert | $octalGroupMode);
                    } else {
                        for ($i = 0; $i < $ugoLen; $i++) {
                            switch ($group[$i]) {
                                case 'u':
                                    $octalGroupMode = $octalGroupMode | $subPerm << 6; // mask xxx000000
                                    break;
                                case 'g':
                                    $octalGroupMode = $octalGroupMode | $subPerm << 3; // mask 000xxx000
                                    break;
                                case 'o':
                                    $octalGroupMode = $octalGroupMode | $subPerm; // mask 000000xxx
                                    break;
                            }
                        }
                        switch ($action) {
                            case '+':
                                $octalMode = $octalMode | $octalGroupMode;
                                break;
                            case '-':
                                $octalMode = $octalMode & ~$octalGroupMode;
                                break;
                        }
                    }
                }
            }
        }
        if (function_exists('fileperms') && $octalMode === (fileperms($file) & 0777)) {
            return true;
        }
        if (!function_exists('chmod')) {
            return false;
        }
        return @chmod($file, $octalMode);
    }
    public static function mkdir($path, $mode = 0777, $recursive = false, $context = null)
    {
        if (strlen($path) > PHP_MAXPATHLEN) {
            throw new Exception('Skipping a file that exceeds allowed max path length [' . PHP_MAXPATHLEN . ']. File: ' . $path);
        }
        if (!file_exists($path)) {
            if (!function_exists('mkdir')) {
                return false;
            }
            if (!@mkdir($path, 0777, $recursive)) {
                return false;
            }
        }
        return self::chmod($path, $mode);
    }
    public static function startsWith($haystack, $needle)
    {
        return $needle === "" || strrpos($haystack, $needle, - strlen($haystack)) !== false;
    }
    public static function hasShellExec()
    {
        if (!Shell::test()) {
            return false;
        }
        return true;
    }
    public static function getUnzipFilePath()
    {
        static $filepath = null;
        if ($filepath === null) {
            if (!self::hasShellExec()) {
                $filepath = false;
            } elseif (Shell::runCommand('hash unzip 2>&1', Shell::AVAILABLE_COMMANDS) !== false) {
                $filepath = 'unzip';
            } else {
                $filepath       = false;
                $possible_paths = array(
                    '/usr/bin/unzip',
                    '/opt/local/bin/unzip',
                    '/bin/unzip',
                    '/usr/local/bin/unzip',
                    '/usr/sfw/bin/unzip',
                    '/usr/xdg4/bin/unzip',
                    '/opt/bin/unzip',
                );
                foreach ($possible_paths as $path) {
                    if (file_exists($path)) {
                        $filepath = $path;
                        break;
                    }
                }
            }
        }
        return $filepath;
    }
    public static function readableByteSize($size)
    {
        try {
            $units = array('B', 'KB', 'MB', 'GB', 'TB');
            for ($i = 0; $size >= 1024 && $i < 4; $i++) {
                $size /= 1024;
            }
            return round($size, 2) . $units[$i];
        } catch (Exception $e) {
            return "n/a";
        }
    }
    public static function rrmdir($path)
    {
        if (is_dir($path)) {
            if (($dh = opendir($path)) === false) {
                return false;
            }
            while (($object = readdir($dh)) !== false) {
                if ($object == "." || $object == "..") {
                    continue;
                }
                if (!self::rrmdir($path . "/" . $object)) {
                    closedir($dh);
                    return false;
                }
            }
            closedir($dh);
            return @rmdir($path);
        } else {
            if (is_writable($path)) {
                return @unlink($path);
            } else {
                return false;
            }
        }
    }
    public static function setSafePath($path)
    {
        return str_replace("\\", "/", $path);
    }
    public static function sanitizeNSCharsNewline($string)
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F\r\n]/u', '', (string) $string);
    }
    public static function classExists($className, $autoload = true)
    {
        if (function_exists("ini_get")) {
            $disabled = explode(',', ini_get('disable_classes'));
            return in_array($className, $disabled) ? false : true;
        }
        if (!class_exists($className, $autoload)) {
            return false;
        }
        return true;
    }
    public static function getBridgeHash()
    {
        $key  = 'qLtOw7WpuK';
        $key .= (defined('DB_NAME') ? DB_NAME : '');
        $key .= (defined('DB_USER') ? DB_USER : '');
        $key .= (defined('DB_PASSWORD') ? DB_PASSWORD : '');
        $key .= (defined('DB_HOST') ? DB_HOST : '');
        $key .= '89aEO7NVO0';
        return md5($key);
    }
}
}
namespace Duplicator\Installer\Bootstrap {
use Duplicator\Libs\DupArchive\DupArchive;
class BootstrapView
{
    const VIEW_ERROR    = 'error';
    const VIEW_REDIRECT = 'redirect';
    const VIEW_PASSWORD = 'pwd';
    protected $boot = null;
    public function __construct()
    {
        $this->boot = BootstrapRunner::getInstance();
    }
    public function redirectToInsaller()
    {
        $this->renderHeader();
        ?>
        <div style="text-align: center; margin: 150px 0; font-size: 20px;">
            <b>Initializing Installer. Please wait...</b>
        </div>
        <?php
        echo $this->boot->getRedirectForm();
        $this->renderFooter();
    }
    public function renderPassword()
    {
        $this->renderHeader();
        $errorMsg = $this->boot->getErrorMessage();
        if (strlen($errorMsg) > 0) {
            $this->renderTopMessages($errorMsg);
        }
        ?>
        <form id="archive_password_required" method="post" >
            <div id="pwd-check-fail" class="error-pane no-display">
                <p>
                    <?php echo $errorMsg; ?>
                </p>
            </div>
            <div id="header-main-wrapper">
                <div class="hdr-main">
                    Password Required
                </div>
            </div>
            <div class="margin-top-0 margin-bottom-2">
                <div id="pass-quick-help-info" class="box info">
                    This archive was created with an encryption enabled password.  Please provide the password to  extract the archive file.<br/>
                    <small>
                        Lost passwords for encrypted archives cannot be recovered by support.
                        If the password was lost then a new archive will need to be created.
                    </small>
                </div>
            </div>
            <div class="dupx-opts" >
                <div id="wrapper_item_secure-pass" class="param-wrapper param-form-type-pwdtoggle margin-bottom-2 has-main-label">
                    <label class="container">
                        <span class="label main-label">Password:</span>
                        <span class="input-container">
                            <span class="input-item input-password-group input-postfix-btn-group">
                                <input 
                                    value="" 
                                    type="password" 
                                    maxlength="150" 
                                    name="<?php echo BootstrapRunner::NAME_PWD; ?>" 
                                    id="param_item_secure-pass" 
                                    autocomplete="off"
                                >
                                <button type="button" class="postfix" title="Show the password">
                                    <?php $this->renderEyeFont(); ?>
                                    <?php $this->renderEyeSlashFont(); ?>
                                </button>
                            </span>
                        </span>
                    </label>
                </div>
            </div>
            <div class="footer-buttons" >
                <div class="content-center" >
                    <button type="submit" name="secure-btn" value="secure" id="secure-btn" class="default-btn" >
                        Submit
                    </button>
                </div>
            </div>
        </form>
        <script>
            var button = document.querySelector('#wrapper_item_secure-pass button.postfix');
            var inputPwd = document.querySelector('#param_item_secure-pass');
            var eye =  document.querySelector('#wrapper_item_secure-pass .icon_eye');
            var eye_slash =  document.querySelector('#wrapper_item_secure-pass .icon_eye_slash');
            inputPwd.focus();
            button.onclick = function changeContent() {
                if (inputPwd.getAttribute('type') === 'password') {
                    inputPwd.setAttribute("type", "text");
                    eye.classList.add('no-display');
                    eye_slash.classList.remove('no-display');
                } else {
                    inputPwd.setAttribute("type", "password");
                    eye.classList.remove('no-display');
                    eye_slash.classList.add('no-display');
                }
            }
        </script>
        <?php
        $this->renderFooter();
    }
    protected function renderTopMessages($message)
    {
        ?>
        <div id="page-top-messages">
            <div class="notice next-step l-critical">
                <?php $this->renderExclamationCircle(); ?>
                <div class="title">
                    <b><?php echo $message; ?></b>
                </div>
            </div>
        </div>
        <?php
    }
    public function renderError()
    {
        $auto_refresh = isset($_POST['auto-fresh']) ? true : false;
        $this->renderHeader();
        ?>
        <h2 style="color:maroon">Setup Notice:</h2>
        <div class="errror-notice">An error has occurred. In order to load the full installer please resolve the issue below.</div>
        <div class="errror-msg">
            <?php echo $this->boot->getErrorMessage(); ?>
        </div>
        <br/><br/>
        <h2>Server Settings:</h2>
        <table class='settings'>
            <?php if ($this->boot->isZip()) { ?>
                <tr>
                    <td>ZipArchive:</td>
                    <td><?php echo BootstrapUtils::isPhpZipAvaiable() ? '<i class="pass">Enabled</i>' : '<i class="fail">Disabled</i>'; ?> </td>
                </tr>
                <tr>
                    <td>ShellExec&nbsp;Unzip:</td>
                    <td><?php echo BootstrapUtils::isShellZipAvailable() ? '<i class="pass">Enabled</i>' : '<i class="fail">Disabled</i>'; ?> </td>
                </tr>
            <?php } else { ?>
                <tr>
                    <td>PHP OpenSSL Module:</td>
                    <td><?php echo DupArchive::isEncryptionAvaliable() ? '<i class="pass">Enabled</i>' : '<i class="fail">Disabled</i>'; ?> </td>
                </tr>                
            <?php } ?>
            <tr>
                <td>Extraction&nbsp;Path:</td>
                <td><?php echo $this->boot->targetRoot; ?></td>
            </tr>
            <tr>
                <td>Installer Path:</td>
                <td><?php echo $this->boot->targetDupInstFolder; ?></td>
            </tr>
            <tr>
                <td>Archive Size:</td>
                <td>
                    <b>Expected Size:</b> <?php echo BootstrapUtils::readableByteSize($this->boot->archiveExpectedSize); ?>  &nbsp;
                    <b>Actual Size:</b>   <?php echo BootstrapUtils::readableByteSize($this->boot->archiveActualSize); ?>
                </td>
            </tr>
            <tr>
                <td>Boot Log</td>
                <td>
                    <a target='_blank' href="<?php echo basename($this->boot->getBootLogFilePath()); ?>" >
                        dup-installer-bootlog__[HASH].txt
                    </a>
                </td>
            </tr>
        </table>
        <br/><br/>
        <div style="font-size:11px">
            Please Note: ZipArchive or ShellExec Unzip will need to be enabled for the installer to
            run automatically otherwise a manual extraction
            will need to be performed. In order to run the installer manually follow the instructions to
            <a href='https://snapcreek.com/duplicator/docs/faqs-tech/#faq-installer-015-q' target='_blank'>
                manually extract
            </a> before running the installer.
        </div>
        <script>
            function AutoFresh() {
                document.getElementById('error-form').submit();
            }
        <?php if ($auto_refresh) : ?>
                var duration = 10000; //10 seconds
                var counter = 10;
                var countElement = document.getElementById('count-down');
                setTimeout(function () {
                    window.location.reload(1);
                }, duration);
                setInterval(function () {
                    counter--;
                    countElement.innerHTML = (counter > 0) ? counter.toString() : "0";
                }, 1000);
        <?php endif; ?>
        </script>
        <?php
        $this->renderFooter();
    }
    protected function renderHeader()
    {
        ?><!DOCTYPE html>
        <html>
            <head>
                <meta name="robots" content="noindex,nofollow">
                <title>Duplicator Pro Installer</title>
                <link rel="icon" href="data:;base64,iVBORw0KGgo=">
                <?php $this->renderCSS(); ?>
            </head>
            <body>
                <div id="content">
                    <table cellspacing="0" class="header-wizard">
                        <tr>
                            <td style="width:100%;">
                                <div class="dupx-branding-header">
                                    <?php $this->renderBoltFont(); ?> Duplicator Pro
                                </div>
                            </td>
                            <td class="wiz-dupx-version">
                                version: <?php echo $this->boot->getVersion(); ?>
                                <div style="padding: 6px 0">
                                    <a target='_blank' href="<?php echo basename($this->boot->getBootLogFilePath()); ?>">
                                        dup-installer-bootlog__[HASH].txt
                                    </a>
                                </div>
                            </td>
                        </tr>
                    </table>
                    <div id="content-inner">
        <?php
    }
    protected function renderFooter()
    {
        ?>               
                    </div>
                </div>
            </body>
        <html>
        <?php
    }
    protected function renderBoltFont()
    {
        ?>
        <svg class="icon_bolt" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512" style="height: 23px;">
            <path d="M296 160H180.6l42.6-129.8C227.2 15 215.7 0 200 0H56C44 0 33.8 8.9 32.2 20.8l-32 240C-1.7 275.2 9.5 288 24 288h118.7L96.6 482.5c-3.6 15.2 8 29.5 23.3 29.5 8.4 0 16.4-4.4 20.8-12l176-304c9.3-15.9-2.2-36-20.7-36z"/><?php // phpcs:ignore ?>
        </svg>
        <?php
    }
    protected function renderEyeFont()
    {
        ?>
        <svg class="icon_eye" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" style="height: 16px;">
            <path d="M572.52 241.4C518.29 135.59 410.93 64 288 64S57.68 135.64 3.48 241.41a32.35 32.35 0 0 0 0 29.19C57.71 376.41 165.07 448 288 448s230.32-71.64 284.52-177.41a32.35 32.35 0 0 0 0-29.19zM288 400a144 144 0 1 1 144-144 143.93 143.93 0 0 1-144 144zm0-240a95.31 95.31 0 0 0-25.31 3.79 47.85 47.85 0 0 1-66.9 66.9A95.78 95.78 0 1 0 288 160z"/> <?php // phpcs:ignore ?>
        </svg>
        <?php
    }
    protected function renderEyeSlashFont()
    {
        ?>
        <svg class="icon_eye_slash no-display" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512" style="height: 16px;" >
            <path d="M320 400c-75.85 0-137.25-58.71-142.9-133.11L72.2 185.82c-13.79 17.3-26.48 35.59-36.72 55.59a32.35 32.35 0 0 0 0 29.19C89.71 376.41 197.07 448 320 448c26.91 0 52.87-4 77.89-10.46L346 397.39a144.13 144.13 0 0 1-26 2.61zm313.82 58.1l-110.55-85.44a331.25 331.25 0 0 0 81.25-102.07 32.35 32.35 0 0 0 0-29.19C550.29 135.59 442.93 64 320 64a308.15 308.15 0 0 0-147.32 37.7L45.46 3.37A16 16 0 0 0 23 6.18L3.37 31.45A16 16 0 0 0 6.18 53.9l588.36 454.73a16 16 0 0 0 22.46-2.81l19.64-25.27a16 16 0 0 0-2.82-22.45zm-183.72-142l-39.3-30.38A94.75 94.75 0 0 0 416 256a94.76 94.76 0 0 0-121.31-92.21A47.65 47.65 0 0 1 304 192a46.64 46.64 0 0 1-1.54 10l-73.61-56.89A142.31 142.31 0 0 1 320 112a143.92 143.92 0 0 1 144 144c0 21.63-5.29 41.79-13.9 60.11z"/> <?php // phpcs:ignore ?>
        </svg>
        <?php
    }
    protected function renderExclamationCircle()
    {
        ?>
        <div class="fas" >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" class="icon_exclamation_circle" style="height: 16px; margin-top: 2px;" >
                <path fill="#FFFFFF" d="M504 256c0 136.997-111.043 248-248 248S8 392.997 8 256C8 119.083 119.043 8 256 8s248 111.083 248 248zm-248 50c-25.405 0-46 20.595-46 46s20.595 46 46 46 46-20.595 46-46-20.595-46-46-46zm-43.673-165.346l7.418 136c.347 6.364 5.609 11.346 11.982 11.346h48.546c6.373 0 11.635-4.982 11.982-11.346l7.418-136c.375-6.874-5.098-12.654-11.982-12.654h-63.383c-6.884 0-12.356 5.78-11.981 12.654z"/> <?php // phpcs:ignore ?>
            </svg>
        </div>
        <?php
    }
    protected function renderCSS()
    {
        ?>
        <style>
            .float-right {
                float: right;
            }
            .float-left {
                float: left;
            }
            .clearfix:before,
            .clearfix:after {
                content: " "; /* 1 */
                display: table; /* 2 */
            }
            .clearfix:after {
                clear: both;
            }
            .no-display { 
                display: none; 
            }
            .hidden {
                visibility: hidden;
                opacity: 0;
            }
            .transparent {
                opacity: 0;
            }
            .monospace {
                font-family: monospace;
            }
            .red {
                color: #AF0000;
            }
            .orangered {
                color: orangered;
            }
            .green {
                color: #008000;
            }
            .maroon {
                color:maroon;
            }
            .silver {
                color:silver;
            }
            .font-size-14 {font-size: 14px}
            .font-size-15 {font-size: 15px}
            .font-size-16 {font-size: 16px}
            .text-center {
                text-align: center;
            }
            .text-right {
                text-align: right;
            }
            .display-inline {
                display: inline;
            }
            .display-inline-block {
                display: inline-block;
            }
            .display-block {
                display: block;
            } 
            .margin-top-0 {
                margin-top: 0;
            }
            .margin-top-1 {
                margin-top: 20px;
            }
            .margin-top-2 {
                margin-top: 40px;
            }
            .margin-top-3 {
                margin-top: 60px;
            }
            .margin-top-4 {
                margin-top: 80px;
            }
            .margin-bottom-0 {
                margin-bottom: 0;
            }
            .margin-bottom-1 {
                margin-bottom: 20px;
            }
            .margin-bottom-2 {
                margin-bottom: 40px;
            }
            .margin-bottom-3 {
                margin-bottom: 60px;
            }
            .margin-bottom-4 {
                margin-bottom: 80px;
            }
            .margin-left-0 {
                margin-left: 0;
            }
            .margin-left-1 {
                margin-left: 20px;
            }
            .margin-left-2 {
                margin-left: 40px;
            }
            .margin-right-0 {
                margin-right: 0;
            }
            .margin-left-1 {
                margin-right: 20px;
            }
            .margin-left-2 {
                margin-right: 40px;
            }
            .auto-updatable button.postfix {
                min-width: 80px;
            }
            .auto-updatable.autoupdate-enabled button.postfix {
                background-color: #13659C;
                color: #fff;
            }
            hr.separator {
                border: 0 none;
                border-bottom:1px solid #dfdfdf;
                margin: 1em 0;
                padding: 0;
            }
            hr.separator.dotted {
                border-bottom:1px dotted #dfdfdf;
            }
            .text-security-disc {
                font-family: dotsfont !important;
                font-size: 10px;
            }
            .text-security-disc::-webkit-input-placeholder {
                font-family: Verdana, Arial, sans-serif !important;
                font-size: 13px;
            }
            .text-security-disc::-ms-input-placeholder {
                font-family: Verdana, Arial, sans-serif !important;
                font-size: 13px;
            }
            .text-security-disc::-moz-placeholder {
                font-family: Verdana, Arial, sans-serif !important;
                font-size: 13px;
            }
            .text-security-disc::placeholder {
                font-family: Verdana, Arial, sans-serif !important;
                font-size: 13px;
            }
            body {
                background-color:transparent;
                color: #000000;
                font-family:Verdana,Arial,sans-serif; 
                font-size:13px
            }
            fieldset {border:1px solid silver; border-radius:3px; padding:10px}
            h3 {
                margin:1px; 
                padding:1px; 
                font-size:13px;
            }
            .generic-box .box-title,
            .hdr-sub1 {
                font-size: 18px;
                font-weight: bold;
            }
            .sub-title {
                font-size:14px;
                margin-bottom: 5px;
            }
            .link-style,
            a {
                text-decoration: underline;
                color: #222;
                transition: all 0.3s;
                cursor: pointer;
            }
            .link-style:hover,
            a:hover{
                color: #13659C;
            }
            .margin-top {
                margin-top: 20px;
            }
            *:focus {
                outline: none !important;
            }
            input:not([type=checkbox]):not([type=radio]):not([type=button]):not(.select2-search__field) , select {
                min-width: 0;
                width: 100%;
                border-radius: 2px;
                border: 1px solid silver;
                padding: 4px;
                padding-left: 4px;
                font-family: Verdana,Arial,sans-serif;
                line-height: 20px;
                height: 30px;
                box-sizing: border-box;
                background-color: white;
                color: black;
                border-radius: 4px;
            }
            input:not([type=checkbox]):not([type=radio]):not([type=button]).w30 , select.w30 {
                width: 30%;
            }
            input:not([type=checkbox]):not([type=radio]):not([type=button]).w50 , select.w50 {
                width: 50%;
            }
            input:not([type=checkbox]):not([type=radio]):not([type=button]).w95 , select.w95 {
                width: 95%;
            }
            input[readonly]:not([type=checkbox]):not([type=radio]):not([type=button]) {
                background-color: #efefef;
                color: #999999;
                cursor: not-allowed;
            }
            textarea[readonly] {
                background-color: #efefef;
            }
            .copy-to-clipboard-block textarea {
                width: 100%;
                height: 100px;
            }
            .copy-to-clipboard-block button {
                font-size: 14px;
                padding: 5px 8px;
                margin-bottom: 15px;
            }
            select[size]:not([size="1"]) {
                height: auto;
                line-height: 25px;
            }
            select , option {
                color: black;
            }
            select option {
                padding: 5px;
            }
            input:not([type=checkbox]):not([type=radio]):not([type=button]):disabled,
            select:disabled,
            select option:disabled,
            select:disabled option, 
            select:disabled option:focus,
            select:disabled option:active,
            select:disabled option:checked {
                background: #EBEBE4;
                color: #ccc;
                cursor: not-allowed;
            }
            select:disabled,
            select option:disabled,
            select:disabled option, 
            select:disabled option:focus,
            select:disabled option:active,
            select:disabled option:checked  {
                text-decoration: line-through;
            }
            .option-group.option-disabled {
                color: #ccc;
                cursor: not-allowed;
            }
            button.no-layout {
                background: none;
                border: none;
            }
            .input-postfix-btn-group {
                display: flex;
                border: 1px solid darkgray;
                border-radius: 4px;
                overflow: hidden;
            }
            .input-postfix-btn-group input:not([type=checkbox]):not([type=radio]):not([type=button]) {
                flex: 1 1 0;
                border-radius: 0;
                border: 0 none;
                border-right: 1px solid darkgray;
                height: 28px;
            }
            .input-postfix-btn-group .prefix,
            .input-postfix-btn-group .postfix {
                flex: none;
                min-width: 60px;
                box-sizing: border-box;
                padding: 0 10px;
                margin: 0;
                border: 0 none;
                background-color:#CDCDCD;
            }
            .param-wrapper-disabled .input-postfix-btn-group .prefix,
            .param-wrapper-disabled .input-postfix-btn-group .postfix {
                color: #999999;
                pointer-events: none;
                cursor: not-allowed;
            }
            .param-wrapper.small .input-postfix-btn-group .prefix,
            .param-wrapper.small .input-postfix-btn-group .postfix {
                min-width: 0;
            }
            .input-postfix-btn-group button {
                cursor: pointer;
            }
            .input-postfix-btn-group button:hover {
                border: 0 none;
                background-color: #13659C;
                color: white;
            }
            .param-wrapper span .checkbox-switch {
                top: 2px;
            }
            .param-wrapper.align-right {
                float: right;
            }
            .param-wrapper.align-right > .container > .main-label {
                width: auto;
            }
            .wpinconf-check-wrapper {
                flex: none;
                width: 100px;
            }
            #wrapper_item_subsite_id.param-wrapper-disabled,
            #wrapper_item_subsite_owr_id.param-wrapper-disabled,
            #wrapper_item_subsit_owr_slug.param-wrapper-disabled,
            #wrapper_item_users_mode.param-wrapper-disabled {
                display: none;
            }
            .btn-group {
                display: inline-flex;
                border: 1px solid silver;
                border-radius: 5px;
                overflow: hidden;
            }
            .btn-group button {
                flex: 1 1 0;
                background-color: #E4E4E4; 
                border: 0 none !important;
                border-right: 1px solid silver !important;
                padding: 6px; 
                cursor: pointer; 
                float: left;
                font-size: 14px;
            }
            .overwrite_sites_list {
                display: flex;
                flex-direction: column;
                row-gap: 20px;
            }
            .param-form-type-sitesowrmap .overwrite_site_item {
                display: flex;
                flex-wrap: wrap;
                gap: 5px 20px;
            }
            .param-form-type-sitesowrmap .overwrite_site_item .del_item {
                float: right;
                font-size: 25px;
                line-height: 1;
            }
            .param-form-type-sitesowrmap .overwrite_site_item .del_item.disabled {
                color: silver;
            }
            .param-form-type-sitesowrmap .overwrite_site_item > .col {
                flex: 1 1 0;
            }
            .param-form-type-sitesowrmap .overwrite_site_item.title > .col {
                border-bottom: 1px solid #D3D3D3;
                padding-bottom: 5px;
                font-weight: bold;
            }
            .param-form-type-sitesowrmap .overwrite_site_item > .col.del {
                flex-grow: 0;
                font-size: 18px;
                border:none;
            }
            .param-form-type-sitesowrmap .overwrite_sites_list.no-multiple .overwrite_site_item > .col.del,
            .param-form-type-sitesowrmap .overwrite_sites_list.no-multiple .overwrite_site_item.add_item {
                display: none;
            }
            .param-form-type-sitesowrmap .overwrite_site_item > .full {
                flex: 0 0 100%;
            }
            .param-form-type-sitesowrmap .target_select_wrapper {
                position: relative;
            }
            .param-form-type-sitesowrmap .target_select_wrapper .new-slug-wrapper {
                position: absolute;
                top: 0;
                right: 22px;
                width: 280px;
            }
            .param-form-type-sitesowrmap .target_select_wrapper .new-slug-wrapper  input {
                background: #EFEFEF;
                border-radius: 0;
            }
            .param-form-type-sitesowrmap .sub-note {
                word-wrap: anywhere;
            }
            .param-form-type-sitesowrmap .sub-note .site-slug {
                font-weight: bold;
                display: inline-block;
                padding: 2px;
                background: #EFEFEF;
                border-radius: 2px;
            }
            .btn-group.small button {
                padding: 3px 7px 3px 7px;
                font-size: 11px;
            }
            .btn-group button:last-child {
                border-right: none !important; 
            }
            .btn-group:after {
                content: "";
                clear: both;
                display: table;
            }
            .btn-group button:hover,
            .btn-group button.active {
                background-color: #13659C;
                color: #FFF;
            }
            .box {
                border: 1px solid silver;
                padding: 10px;
                background: #f9f9f9;
                border-radius:2px;
            }
            .box *:first-child {
                margin-top: 0;
            }
            .box *:last-child {
                margin-bottom: 0;
            }
            .box.warning {
                color: maroon;
                border-color: maroon;
            }
            body,
            div#content,
            form.content-form {
                line-height: 1.5;
            }
            #content {
                border:1px solid #CDCDCD; 
                margin: 20px auto; 
                border-radius:2px;
                box-shadow:0 8px 6px -6px #999;
                font-size:13px;
                width: calc(900px + 42px);
                max-width: calc(100vw - 40px);
                box-sizing: border-box;
            }
            .debug-params div#content {
                margin: 20px; 
            }
            #content-inner {
                margin: 20px;
                position: relative;
            }
            #content-loader-wait {        
                font-weight: bold;
                text-align: center;
                vertical-align: middle;
            }
            #body-step4 #content-inner {
                padding-bottom: 0;
            }
            div.logfile-link {float:right; font-weight:normal; font-size:11px; font-style:italic}
            table.header-wizard {width:100%; box-shadow:0 5px 3px -3px #999; background-color:#E0E0E0; font-weight:bold}
            .wiz-dupx-version {
                white-space:nowrap; 
                color:#777; 
                font-size:11px; 
                font-style:italic; 
                text-align:right;  
                padding:5px 15px 5px 0; 
                line-height:14px; 
                font-weight:normal
            }
            .wiz-dupx-version a { color:#999; }
            div.dupx-branding-header {font-size:26px; padding: 10px 0 7px 15px;}
            .dupx-overwrite {color:black;}
            .dupx-pass {display:inline-block; color:green;}
            .dupx-fail {display:inline-block; color:#AF0000;}
            .dupx-warn {display:inline-block; color:#555;}
            .dupx-notice {display:inline-block; color:#000;}
            i[data-tooltip].fa-question-circle {cursor: pointer; color:#888888}
            #wrapper_item_install-type input[value="6"] + .label-checkbox::after,
            #wrapper_item_install-type input[value="7"] + .label-checkbox::after
            {
                content: "Beta";
                border-radius:4px; 
                color:#fff; 
                padding:0 3px 0 3px;  
                font-size:11px; 
                min-width:30px; 
                text-align:center; 
                font-weight:normal;
                background-color:maroon;
                padding: 2px 4px;
            }
            .status-badge {
                border-radius:4px; 
                color:#fff; 
                padding:0 3px 0 3px;  
                font-size:11px; 
                min-width:30px; 
                text-align:center; 
                font-weight:normal;
            }
            .status-badge.right {
                float: right; 
            }
            .status-badge.pass,
            .status-badge.good,
            .status-badge.success {
                background-color:#418446
            }
            .status-badge.pass::after {
                content: "Pass"
            }
            .status-badge.good::after {
                content: "Pass"
            }
            .status-badge.success::after {
                content: "Success"
            }
            .status-badge.fail {
                background-color:maroon;
            }
            .status-badge.fail::after {
                content: "Fail"
            }
            .status-badge.hwarn {
                background-color: #a15e19;
            }
            .status-badge.hwarn::after {
                content: "Warn"
            }
            .status-badge.warn {
                background-color: #555555;
            }
            .status-badge.warn::after {
                content: "Notice"
            }
            .default-btn,
            .secondary-btn {
                transition: all 0.2s ease-out;
                color: #FEFEFE;
                font-size: 16px;
                border-radius: 5px;
                padding: 7px 15px;
                background-color: #13659C;
                border: 1px solid gray;
                line-height: 18px;
                text-decoration: none;
                display: inline-block;
                white-space: nowrap;
                min-width: 100px;
                text-align: center;
            }
            .default-btn.small,
            .secondary-btn.small {
                font-size: 13px;
                padding: 3px 10px;
                min-width: 80px;
            }
            .default-btn:hover {
                color: #13659C;
                border-color: #13659C;
                background-color: #FEFEFE;
            }
            .default-btn.disabled,
            .default-btn:disabled {
                color:silver;         
                background-color: #EDEDED;
                border: 1px solid silver;
            }
            .secondary-btn {
                color: #333333;         
                background-color: #EDEDED;
                border: 1px solid #333333;
            }
            .secondary-btn:hover {
                color: #FEFEFE;         
                background-color: #999999;
            }
            .log-ui-error {padding-top:2px; font-size:13px}
            #progress-area {
                padding:5px; 
                margin:150px 0; 
                text-align:center;
            }
            .progress-text {font-size:1.7em; margin-bottom:20px}
            #secondary-progress-text { font-size:.85em; margin-bottom:20px }
            #progress-notice:not(:empty) { color:maroon; font-size:.85em; margin-bottom:20px; }
            #ajaxerr-data {
                min-height: 300px;
            }
            #ajaxerr-data .pre-content,
            #ajaxerr-data .html-content {
                padding:6px; 
                box-sizing: border-box;
                width:100%; 
                border:1px solid silver; 
                border-radius:3px;
                background-color:#F1F1F1; 
                overflow-y:scroll; 
                line-height:20px
            }
            #ajaxerr-data .pre-content {
                height:300px;
            }
            #ajaxerr-data .iframe-content {
                width: 100%;
                height: 300px;
                overflow: auto;
                box-sizing: border-box;
                border:1px solid silver;
                border-radius:3px;
            }
            #header-main-wrapper {
                position: relative;
                padding:0 0 5px 0; 
                border-bottom:1px solid #D3D3D3; 
                margin: 0 0 20px 0;
                display: flex;
            }
            #header-main-wrapper .dupx-logfile-link {
                font-weight:normal; 
                font-style:italic; 
                font-size:11px;
                position: absolute;
                bottom: 2px;
                right: 0;
            }
            #header-main-wrapper .hdr-main {
                font-size:22px; 
                font-weight:bold; 
                flex: 1 1 auto;
            }
            #header-main-wrapper .hdr-secodary {
                flex: 0 1 auto;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                min-width: 150px;
            }
            .hdr-secodary .installer-log {
                font-size: 12px;
                font-style: italic;
                text-align: right;
            }
            #installer-switch-wrapper  {
                text-align:right
            }
            #installer-switch-wrapper .btn-group {
                width: 120px;
            }
            .generic-box { 
                border: 1px solid #DEDEDE;
                border-radius: 2px;
                margin-bottom: 20px;
            }
            .generic-box .box-title { 
                padding: 4px 7px;
                border-bottom: 1px solid #DEDEDE;
                background-color:#f9f9f9; 
                border-radius:2px 2px 0 0;
            }
            .generic-box .box-content { 
                padding: 20px;
            }
            .generic-box .box-content *:first-child {
                margin-top: 0;
            }
            .generic-box .box-content *:last-child {
                margin-bottom: 0;
            }
            div.sub-header {
                font-size:11px; 
                font-style:italic; 
                font-weight:normal
            }
            .hdr-main .step { 
                color:#DB4B38  
            }
            .hdr-sub1 {
                border:1px solid #D3D3D3;
                padding: 4px 7px;
                background-color:#E0E0E0;
                border-radius:2px 2px 0 0;
            }
            .hdr-sub1.open {
                border-radius: 2px;
                margin-bottom: 20px;
            }
            .hdr-sub1 a {cursor:pointer; text-decoration: none !important}
            .hdr-sub1 i.fa,
            .hdr-sub1 i.fas,
            .box-title i.fa,
            .box-title i.fas {
                font-size:15px; 
                display:inline-block; 
                margin-right:5px; 
                position: relative;
                bottom: 1px;
            }
            .hdr-sub1 .status-badge {
                margin-top: 4px;
            }
            .hdr-sub1-area {
                border: 0 solid #D3D3D3;
                border-top: 0 none;
                border-radius: 0 0 2px 2px;
                padding: 20px;
                margin-bottom: 20px;
                position: relative;
                background-color:#fff;
            }
            .hdr-sub1-area.tabs-area {
                padding: 5px 5px 0 5px;
            }
            .hdr-sub1-area.tabs-area .ui-tabs-nav {
                border-radius: 0;
                border: 0 none;
            }
            .hdr-sub1-area.tabs-area .ui-tabs {
                margin: 0;
                padding: 0;
                border: 0 none;
            }
            .hdr-sub1-area.tabs-area .ui-tabs-tab {
                margin: 3px 5px 0 0;
            }
            .hdr-sub1-area.tabs-area .ui-tabs-panel {
                position: relative;
                padding: 10px;
            }
            .hdr-sub2 {font-size:15px; padding:2px 2px 2px 0; font-weight:bold; margin-bottom:5px; border:none}
            .hdr-sub3 {font-size:15px; padding:2px 2px 2px 0; border-bottom:1px solid #D3D3D3; font-weight:bold; margin-bottom:5px;}
            .hdr-sub3.warning::before {
                content: "\f071";
                font-family: "Font Awesome 5 Free";
                font-weight: 900;
                font-style: normal;
                font-variant: normal;
                text-rendering: auto;
                line-height: 1;
                font-size: 12px;
                margin-right: 5px;
                color: #AF0000;
            }
            .hdr-sub4 {font-size:15px; padding:7px; border:1px solid #D3D3D3;; font-weight:bold; background-color:#e9e9e9;}
            .hdr-sub4:hover  {background-color:#dfdfdf; cursor:pointer}
            .toggle-hdr:hover {cursor:pointer; background-color:#f1f1f1; border:1px solid #dcdcdc; }
            .toggle-hdr:hover a{color:#000}
            .ui-widget-header {border: none; border-bottom: 1px solid #D3D3D3 !important; background:#fff}
            [data-type="toggle"] > i.fa,
            i.fa.fa-toggle-empty { min-width: 8px; }
            .notice {
                background: #fff;
                border:1px solid #dfdfdf;
                border-left: 4px solid #fff;
                margin: 5px 0;
                padding: 5px;
                border-radius: 2px;
                font-size: 12px;
            }
            .section .notice:first-child {
                margin-top: 0;
            }
            .section .notice:last-child {
                margin-bottom: 0;
            }
            .notice.next-step {
                margin: 20px 0;
                padding: 10px;
            }
            .notice-report {
                border-left: 4px solid #fff;
                padding-left: 0;
                padding-right: 0;
                margin-bottom: 4px;
            }
            .next-step .title-separator {
                margin-top: 10px;
                padding-top: 10px;
                border-top: 1px solid lightgray;
            }
            .notice .info pre {
                margin: 0;
                padding: 0 0 10px 0;
                overflow: auto;
            }
            .notice-report .title {
                padding: 0 10px;
            }
            .notice-report .title.close {
                padding-bottom: 5px;
            }
            .notice-report .info {
                border-top: 1px solid #dedede;
                padding: 10px;
                background: #FAFAFA;
            }
            .notice-report .info *:first-child {
                margin-top: 0;
            }
            .notice-report .info *:last-child{
                margin-bottom: 0;
            }
            .notice-report .info pre {
                font-size: 11px;
            }
            .notice.l-info,
            .notice.l-notice {
                border-left-color: #197b19;
            }
            .notice.l-swarning {
                border-left-color: #636363;
            }
            .notice.l-hwarning {
                border-left-color: #636363;
            }
            .notice.l-critical {
                border-left-color: maroon;
            }
            .notice.l-fatal {
                border-left-color: #000000;
            }
            .notice.next-step {
                position: relative;
            }
            .notice.next-step.l-info,
            .notice.next-step.l-notice {
                border-color: #197b19;
            }
            .notice.next-step.l-swarning {
                border-color: #636363;
            }
            .notice.next-step.l-hwarning {
                border-color: #636363;
            }
            .notice.next-step.l-critical {
                border-color: maroon;
            }
            .notice.next-step.l-fatal {
                border-color: #000000;
            }
            .notice.next-step > .title {
                padding-left: 30px;
            }
            .notice.next-step > .fas {
                display: block;
                position: absolute;
                height: 20px;
                width: 20px;
                line-height: 20px;
                text-align: center;
                color: white;
                border-radius:2px;
            }
            .notice.next-step.l-info > .fas,
            .notice.next-step.l-notice > .fas {
                background-color: #197b19;
            }
            .notice.next-step.l-swarning > .fas {
                background-color: #636363;
            }
            .notice.next-step.l-hwarning > .fas {
                background-color: #636363;
            }
            .notice.next-step.l-critical > .fas {
                background-color: maroon;
            }
            .notice.next-step.l-fatal > .fas{
                background-color: #000000;
            }
            .report-sections-list .section {
                border: 1px solid #DFDFDF;
                margin-bottom: 25px;
                box-shadow: 4px 8px 11px -8px rgba(0,0,0,0.41);
            }
            .report-sections-list .section > .section-title {
                background-color: #efefef;
                padding: 3px;
                font-weight: bold;
                text-align: center;
                font-size: 14px;
            }
            .report-sections-list .section > .section-content {
                padding: 5px;
            }
            .notice-level-status {
                border-radius:2px;
                padding: 2px;
                margin: 1px;
                font-size: 10px;
                display: inline-block;
                color: #FFF;
                font-weight: bold;
                min-width:55px;
            }
            .notice-level-status.l-info,
            .notice-level-status.l-notice {background: #197b19;}
            .notice-level-status.l-swarning {background: #636363;}
            .notice-level-status.l-hwarning {background: #636363;}
            .notice-level-status.l-critical {background: maroon;}
            .notice-level-status.l-fatal {background: #000000;}
            .dupx-opts .param-wrapper {
                padding: 5px 0;
            }
            .dupx-opts .param-wrapper .param-wrapper {
                padding: 0;
            }
            .dupx-opts .param-wrapper.param-form-type-hidden{
                margin: 0;
                padding: 0;
                display: none;
            }
            .param-wrapper-disabled {
                color: #999;
            }
            .param-wrapper > .container {
                display: flex;
                flex-direction: row;
                flex-wrap: nowrap;
                align-items: center;
                min-height: 30px;
            }
            .param-wrapper > .container > .main-label {
                flex: none;
                width: 200px;
                font-weight: bold;
                line-height: 1.5;
                box-sizing: border-box;
                padding-right: 5px;
            }
            .param-wrapper.has-main-label > .sub-note {
                margin-left: 200px;
            }
            #tabs-wp-config-file .param-wrapper > .container > .main-label {
                width: 310px;
            }
            #tabs-wp-config-file .param-wrapper.has-main-label > .sub-note {
                margin-left: 310px;
            }
            #tabs-wp-config-file div.help-target {
                padding-top:10px;
            }
            .param-wrapper > .container .input-container {
                flex: 1 1 auto;
            }
            .param-wrapper.small > .container .input-container {
                max-width: 100px;
            }
            .param-wrapper.medium > .container .input-container {
                max-width: 300px;
            }
            .param-wrapper.large > .container .input-container {
                max-width: 500px;
            }
            .param-wrapper.full > .container .input-container {
                max-width: none;
            }
            .param-form-type-radio .option-group {
                display: inline-block;
                min-width: 140px;
            }
            .param-form-type-radio.group-block .option-group {
                display: block;
                line-height: 30px;
            }
            .param-wrapper .sub-note {
                display: block;
                font-size: 11px;
                margin-top:6px;
            }
            .param-wrapper .option-group .sub-note {
                line-height: 1.1;
                margin-top: 0;
                margin-bottom: 8px;
                color: #000000;
            }
            #pass-quick-help-info small {
               color:gray;
               font-style: italic
            }
            .main-form-content {
                min-height: 300px;
            }
            .footer-buttons {
                display: flex;
                width: 100%;
            }
            .footer-buttons .content-left,
            .footer-buttons .content-center {
                flex: 1;
            }
            .footer-buttons .content-center {
                text-align: center;
            }
            h2 {font-size:20px; margin:5px 0 5px 0; border-bottom:1px solid #dfdfdf; padding:3px}
            div.errror-notice {text-align:center; font-style:italic; font-size:11px}
            div.errror-msg { color:maroon; padding: 10px 0 5px 0}
            .pass {color:green}
            .fail {color:red}
            span.file-info {font-size: 11px; font-style: italic}
            div.skip-not-found {padding:10px 0 5px 0;}
            div.skip-not-found label {cursor: pointer}
            table.settings {width:100%; font-size:12px}
            table.settings td {padding: 4px}
            table.settings td:first-child {font-weight: bold}
            .w3-light-grey,.w3-hover-light-grey:hover,.w3-light-gray,.w3-hover-light-gray:hover{
                color:#000!important;background-color:#f1f1f1!important
            }
            .w3-container:after,.w3-container:before,.w3-panel:after,
            .w3-panel:before,.w3-row:after,.w3-row:before,
            .w3-row-padding:after,.w3-row-padding:before,
            .w3-cell-row:before,.w3-cell-row:after,
            .w3-clear:after,.w3-clear:before,.w3-bar:before,.w3-bar:after {
                content:"";display:table;clear:both
            }
            .w3-green,.w3-hover-green:hover{color:#fff!important;background-color:#4CAF50!important}
            .w3-container{padding:0.01em 16px}
            .w3-center{display:inline-block;width:auto; text-align: center !important}
        </style>
        <?php
    }
}
}
namespace Duplicator\Installer\Bootstrap {
use Exception;
class LogHandler
{
    private static $initialized = false;
    private static $logCallback = null;
    public static function initErrorHandler($logCallback)
    {
        if (!self::$initialized) {
            if (!is_callable($logCallback)) {
                throw new Exception('Log callback must be callable');
            }
            self::$logCallback = $logCallback;
            @set_error_handler(array(__CLASS__, 'error'));
            @register_shutdown_function(array(__CLASS__, 'shutdown'));
            self::$initialized = true;
        }
    }
    public static function error($errno, $errstr, $errfile, $errline)
    {
        switch ($errno) {
            case E_ERROR:
                $log_message = self::getMessage($errno, $errstr, $errfile, $errline);
                if (call_user_func(self::$logCallback, $log_message) === false) {
                    $log_message = "Can\'t wrinte logfile\n\n" . $log_message;
                }
                die('<pre>' . htmlspecialchars($log_message) . '</pre>');
                break;
            case E_NOTICE:
            case E_WARNING:
            default:
                $log_message = self::getMessage($errno, $errstr, $errfile, $errline);
                call_user_func(self::$logCallback, $log_message);
                break;
        }
    }
    private static function getMessage($errno, $errstr, $errfile, $errline)
    {
        $result = '[PHP ERR]';
        switch ($errno) {
            case E_ERROR:
                $result .= '[FATAL]';
                break;
            case E_WARNING:
                $result .= '[WARN]';
                break;
            case E_NOTICE:
                $result .= '[NOTICE]';
                break;
            default:
                $result .= '[ISSUE]';
                break;
        }
        $result .= ' MSG:';
        $result .= $errstr;
        $result .= ' [CODE:' . $errno . '|FILE:' . $errfile . '|LINE:' . $errline . ']';
        return $result;
    }
    public static function shutdown()
    {
        if (($error = error_get_last())) {
            LogHandler::error($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }
}
}
namespace Duplicator\Installer\Utils {
use Exception;
class SecureCsrf
{
    const PREFIX = '_DUPX_CSRF';
    private static $packagHash = null;
    private static $mainFolder = null;
    private static $CSRFVars = null;
    public static function init($mainFolderm, $packageHash)
    {
        self::$mainFolder = $mainFolderm;
        self::$packagHash = $packageHash;
        self::$CSRFVars   = null;
    }
    public static function setKeyVal($key, $val)
    {
        self::getCSRFVars();
        self::$CSRFVars[$key] = $val;
        self::saveCSRFVars();
    }
    public static function removeKeyVal($key, $val)
    {
        self::getCSRFVars();
        if (isset(self::$CSRFVars[$key])) {
            unset(self::$CSRFVars[$key]);
            self::saveCSRFVars();
        }
    }
    public static function getVal($key)
    {
        self::getCSRFVars();
        if (isset(self::$CSRFVars[$key])) {
            return self::$CSRFVars[$key];
        } else {
            return false;
        }
    }
    public static function generate($form = null)
    {
        $keyName       = self::getKeyName($form);
        $existingToken = self::getVal($keyName);
        if (false !== $existingToken) {
            $token = $existingToken;
        } else {
            $token = self::token() . self::fingerprint();
        }
        self::setKeyVal($keyName, $token);
        return $token;
    }
    public static function check($token, $form = null)
    {
        if (empty($form)) {
            return false;
        }
        $keyName = self::getKeyName($form);
        self::getCSRFVars();
        return (isset(self::$CSRFVars[$keyName]) && self::$CSRFVars[$keyName] == $token);
    }
    protected static function token()
    {
        $microtime = (int) (microtime(true) * 10000);
        mt_srand($microtime);
        $charid = strtoupper(md5(uniqid(rand(), true)));
        return substr($charid, 0, 8) . substr($charid, 8, 4) . substr($charid, 12, 4) . substr($charid, 16, 4) . substr($charid, 20, 12);
    }
    protected static function fingerprint()
    {
        return strtoupper(md5(implode('|', array($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']))));
    }
    private static function getKeyName($form)
    {
        return self::PREFIX . '_' . $form;
    }
    private static function getPackageHash()
    {
        if (is_null(self::$packagHash)) {
            throw new Exception('Not init CSFR CLASS');
        }
        return self::$packagHash;
    }
    public static function getFilePath()
    {
        if (is_null(self::$mainFolder)) {
            throw new Exception('Not init CSFR CLASS');
        }
        $dupInstallerfolderPath = self::$mainFolder;
        $packageHash            = self::getPackageHash();
        $fileName               = 'dup-installer-csrf__' . $packageHash . '.txt';
        $filePath               = $dupInstallerfolderPath . '/' . $fileName;
        return $filePath;
    }
    private static function getCSRFVars()
    {
        if (is_null(self::$CSRFVars)) {
            $filePath = self::getFilePath();
            if (file_exists($filePath)) {
                $contents = file_get_contents($filePath);
                if (empty($contents)) {
                    self::$CSRFVars = array();
                } else {
                    $CSRFobjs = json_decode($contents);
                    foreach ($CSRFobjs as $key => $value) {
                        self::$CSRFVars[$key] = $value;
                    }
                }
            } else {
                self::$CSRFVars = array();
            }
        }
        return self::$CSRFVars;
    }
    private static function saveCSRFVars()
    {
        $contents = json_encode(self::$CSRFVars);
        $filePath = self::getFilePath();
        file_put_contents($filePath, $contents);
    }
}
}
namespace Duplicator\Libs\DupArchive {
use Duplicator\Libs\DupArchive\Headers\DupArchiveDirectoryHeader;
use Duplicator\Libs\DupArchive\Headers\DupArchiveFileHeader;
use Duplicator\Libs\DupArchive\Headers\DupArchiveGlobHeader;
use Duplicator\Libs\DupArchive\Headers\DupArchiveHeader;
use Error;
use Exception;
class DupArchive
{
    const EXCEPTION_CODE_FILE_DONT_EXISTS = 10;
    const EXCEPTION_CODE_OPEN_ERROR       = 11;
    const EXCEPTION_CODE_INVALID_PASSWORD = 12;
    const EXCEPTION_CODE_INVALID_MARKER   = 13;
    const EXCEPTION_CODE_INVALID_PARAM    = 14;
    const EXCEPTION_CODE_ADD_ERROR        = 15;
    const EXCEPTION_CODE_EXTRACT_ERROR    = 16;
    const EXCEPTION_CODE_VALIDATION_ERROR = 17;
    const DUPARCHIVE_VERSION  = '5.0.0';
    const INDEX_FILE_NAME     = '__dup__archive__index.json';
    const INDEX_FILE_SIZE     = 2000; // reserver 2K
    const EXTRA_FILES_POS_KEY = 'extraPos';
    const HEADER_TYPE_NONE = 0;
    const HEADER_TYPE_FILE = 1;
    const HEADER_TYPE_DIR  = 2;
    const HEADER_TYPE_GLOB = 3;
    const FLAG_COMPRESS = 1; //bitmask
    const FLAG_CRYPT    = 2; //bitmask
    const HASH_ALGO      = 'crc32b';
    const PWD_ALGO       = '$6$rounds=50000$'; // SHA-512 50000 times with salt
    const PWD_SALT_CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#%^-_%^&*()[]{}<>~`+=,.;:/?|';
    const CRYPT_ALGO     = 'AES-256-CBC';
    protected static function getNextHeaderType($archiveHandle)
    {
        $retVal = self::HEADER_TYPE_NONE;
        $marker = fgets($archiveHandle, 4);
        if (feof($archiveHandle) === false) {
            switch ($marker) {
                case '<D>':
                    $retVal = self::HEADER_TYPE_DIR;
                    break;
                case '<F>':
                    $retVal = self::HEADER_TYPE_FILE;
                    break;
                case '<G>':
                    $retVal = self::HEADER_TYPE_GLOB;
                    break;
                default:
                    throw new Exception("Invalid header marker {$marker}. Location:" . ftell($archiveHandle), self::EXCEPTION_CODE_INVALID_MARKER);
            }
        }
        return $retVal;
    }
    public static function isEncrypted($path)
    {
        return !self::checkPassword($path, '');
    }
    public static function checkPassword($path, $password)
    {
        try {
            $header = self::getArchiveHeader($path, $password);
        } catch (Exception $e) {
            if ($e->getCode() == self::EXCEPTION_CODE_INVALID_PASSWORD) {
                return false;
            } else {
                throw $e;
            }
        }
        return true;
    }
    public static function getArchiveHeader($path, $password)
    {
        try {
            $archiveHandle = null;
            if (!file_exists($path)) {
                throw new Exception('Archive file don\'t exists', self::EXCEPTION_CODE_FILE_DONT_EXISTS);
            }
            if (($archiveHandle = fopen($path, 'r')) == false) {
                throw new Exception('Can\'t open archive file', self::EXCEPTION_CODE_OPEN_ERROR);
            }
            $result = (new DupArchiveHeader())->readFromArchive($archiveHandle, $password);
        } finally {
            if (is_resource($archiveHandle)) {
                fclose($archiveHandle);
            }
        }
        return $result;
    }
    public static function isEncryptionAvaliable()
    {
        static $isAvaliable = null;
        if ($isAvaliable === null) {
            $isAvaliable = (
                function_exists('openssl_cipher_iv_length') &&
                function_exists('openssl_encrypt') &&
                function_exists('openssl_decrypt')
            );
        }
        return $isAvaliable;
    }
    public static function getIndexData($archivePath, $password)
    {
        try {
            $indexContent = self::getSrcFile($archivePath, self::INDEX_FILE_NAME, $password, 0, 3000);
            if ($indexContent === false) {
                return false;
            }
            $indexData = json_decode(rtrim($indexContent, "\0"), true);
            if (!is_array($indexData)) {
                return false;
            }
        } catch (Exception $e) {
            return false;
        } catch (Error $e) {
            return false;
        }
        return $indexData;
    }
    public static function getExtraOffset($archivePath, $password)
    {
        if (($indexData = self::getIndexData($archivePath, $password)) === false) {
            return 0;
        }
        return (isset($indexData[self::EXTRA_FILES_POS_KEY]) ? $indexData[self::EXTRA_FILES_POS_KEY] : 0);
    }
    public static function seachPathInArchive($archivePath, $relativePath, $password, $offset = 0, $sizeToSearch = 0)
    {
        try {
            $archiveHandle = null;
            if (($archiveHandle = fopen($archivePath, 'rb')) === false) {
                throw new Exception("Can’t open archive at $archivePath!", self::EXCEPTION_CODE_OPEN_ERROR);
            }
            $archiveHeader = (new DupArchiveHeader())->readFromArchive($archiveHandle, $password);
            $result = self::searchPath($archivePath, $archiveHeader, $relativePath, $offset, $sizeToSearch);
        } finally {
            if (is_resource($archiveHandle)) {
                fclose($archiveHandle);
            }
        }
        return $result;
    }
    public static function searchPath($archiveHandle, DupArchiveHeader $archiveHeader, $relativePath, $offset = 0, $sizeToSearch = 0)
    {
        if (!is_resource($archiveHandle)) {
            throw new Exception('Archive handle must be a resource', self::EXCEPTION_CODE_INVALID_PARAM);
        }
        if (fseek($archiveHandle, $offset, SEEK_SET) < 0) {
            return false;
        }
        if ($offset == 0) {
            $hd = (new DupArchiveHeader())->readFromArchive($archiveHandle, $archiveHeader->getPassword());
        }
        $result   = false;
        $position = ftell($archiveHandle);
        $continue = true;
        do {
            switch (($type = self::getNextHeaderType($archiveHandle))) {
                case self::HEADER_TYPE_FILE:
                    $currentFileHeader = (new DupArchiveFileHeader($archiveHeader))->readFromArchive($archiveHandle, true, true);
                    if ($currentFileHeader->relativePath == $relativePath) {
                        $continue = false;
                        $result   = $position;
                    }
                    break;
                case self::HEADER_TYPE_DIR:
                    $directoryHeader = (new DupArchiveDirectoryHeader($archiveHeader))->readFromArchive($archiveHandle, true);
                    if ($directoryHeader->relativePath == $relativePath) {
                        $continue = false;
                        $result   = $position;
                    }
                    break;
                case self::HEADER_TYPE_NONE:
                    $continue = false;
                    break;
                default:
                    throw new Exception('Invali header type "' . $type . '"', self::EXCEPTION_CODE_INVALID_MARKER);
            }
            $position = ftell($archiveHandle);
            if ($sizeToSearch > 0 && ($position - $offset) >= $sizeToSearch) {
                break;
            }
        } while ($continue);
        if ($result !== false) {
            if (fseek($archiveHandle, $result, SEEK_SET) < 0) {
                return false;
            }
        }
        return $result;
    }
    public static function getSrcFile($archivePath, $relativePath, $password, $offset = 0, $sizeToSearch = 0)
    {
        try {
            $archiveHandle = null;
            if (($archiveHandle = fopen($archivePath, 'rb')) === false) {
                throw new Exception("Can’t open archive at $archivePath!", self::EXCEPTION_CODE_OPEN_ERROR);
            }
            $archiveHeader = (new DupArchiveHeader())->readFromArchive($archiveHandle, $password);
            if (self::searchPath($archiveHandle, $archiveHeader, $relativePath, $offset, $sizeToSearch) === false) {
                return false;
            }
            if (self::getNextHeaderType($archiveHandle) != self::HEADER_TYPE_FILE) {
                return false;
            }
            $header = (new DupArchiveFileHeader($archiveHeader))->readFromArchive($archiveHandle, false, true);
            $result = self::getSrcFromHeader($archiveHandle, $header);
        } finally {
            if (is_resource($archiveHandle)) {
                fclose($archiveHandle);
            }
        }
        return $result;
    }
    protected static function getSrcFromHeader($archiveHandle, DupArchiveFileHeader $fileHeader)
    {
        if ($fileHeader->fileSize == 0) {
            return '';
        }
        $dataSize = 0;
        $result   = '';
        $globHeader = new DupArchiveGlobHeader($fileHeader);
        do {
            $globHeader->readFromArchive($archiveHandle);
            $result   .= $globHeader->readContent($archiveHandle, $globHeader);
            $dataSize += $globHeader->originalSize;
        } while ($dataSize < $fileHeader->fileSize);
        return $result;
    }
    protected static function skipFileInArchive($archiveHandle, DupArchiveFileHeader $fileHeader)
    {
        if ($fileHeader->fileSize == 0) {
            return;
        }
        $dataSize   = 0;
        $globHeader = new DupArchiveGlobHeader($fileHeader);
        do {
            $globHeader->readFromArchive($archiveHandle, true);
            $dataSize += $globHeader->originalSize;
        } while ($dataSize < $fileHeader->fileSize);
    }
    protected static function skipToNextHeader($archiveHandle, DupArchiveHeader $archiveHeader)
    {
        $headerType = self::getNextHeaderType($archiveHandle);
        switch ($headerType) {
            case self::HEADER_TYPE_FILE:
                $fileHeader = (new DupArchiveFileHeader($archiveHeader))->readFromArchive($archiveHandle, false, true);
                self::skipFileInArchive($archiveHandle, $fileHeader);
                break;
            case self::HEADER_TYPE_DIR:
                $directoryHeader = (new DupArchiveDirectoryHeader($archiveHeader))->readFromArchive($archiveHandle, true);
                break;
            case self::HEADER_TYPE_NONE:
                false;
        }
    }
    public static function generateSalt($length)
    {
        $maxRand = (strlen(self::PWD_SALT_CHARS) - 1);
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            if (function_exists('random_int')) {
                $cIndex = random_int(0, $maxRand);
            } else {
                mt_srand(time());
                $cIndex = mt_rand(0, $maxRand);
            }
            $password .= substr(self::PWD_SALT_CHARS, $cIndex, 1);
        }
        return $password;
    }
    public static function encrypt($content, $key)
    {
        static $ivLen = null;
        if ($ivLen === null) {
            if (!self::isEncryptionAvaliable()) {
                throw new Exception('Encryption is unavaiable', self::EXCEPTION_CODE_OPEN_ERROR);
            }
            $ivLen = openssl_cipher_iv_length(DupArchive::CRYPT_ALGO);
        }
        $iv = openssl_random_pseudo_bytes($ivLen);
        if (($result = openssl_encrypt($content, DupArchive::CRYPT_ALGO, $key, OPENSSL_RAW_DATA, $iv)) === false) {
            return false;
        } else {
            return $iv . $result;
        }
    }
    public static function decrypt($content, $key)
    {
        static $ivLen = null;
        if ($ivLen === null) {
            if (!self::isEncryptionAvaliable()) {
                throw new Exception('Encryption is unavaiable', self::EXCEPTION_CODE_OPEN_ERROR);
            }
            $ivLen = openssl_cipher_iv_length(DupArchive::CRYPT_ALGO);
        }
        $iv = substr($content, 0, $ivLen);
        return openssl_decrypt(substr($content, $ivLen), DupArchive::CRYPT_ALGO, $key, OPENSSL_RAW_DATA, $iv);
    }
}
}
namespace Duplicator\Libs\DupArchive {
use Duplicator\Libs\DupArchive\Headers\DupArchiveDirectoryHeader;
use Duplicator\Libs\DupArchive\Headers\DupArchiveFileHeader;
use Duplicator\Libs\DupArchive\Headers\DupArchiveGlobHeader;
use Duplicator\Libs\DupArchive\Headers\DupArchiveHeader;
use Duplicator\Libs\DupArchive\Info\DupArchiveExpanderInfo;
use Exception;
class DupArchiveExpandBasicEngine extends DupArchive
{
    protected static $logCallback   = null;
    protected static $chmodCallback = null;
    protected static $mkdirCallback = null;
    public static function setCallbacks($log, $chmod, $mkdir)
    {
        self::$logCallback   = (is_callable($log) ? $log : null);
        self::$chmodCallback = (is_callable($chmod) ? $chmod : null);
        self::$mkdirCallback = (is_callable($mkdir) ? $mkdir : null);
    }
    public static function log($s, $flush = false)
    {
        if (self::$logCallback == null) {
            return;
        }
        call_user_func(self::$logCallback, "MINI EXPAND:$s", $flush);
    }
    public static function expandDirectory($archivePath, $relativePath, $destPath, $password, $ignoreErrors = false, $offset = 0)
    {
        self::expandItems($archivePath, $relativePath, $destPath, $password, $ignoreErrors, $offset);
    }
    private static function expandItems($archivePath, $inclusionFilter, $destDirectory, $password, $ignoreErrors = false, $offset = 0)
    {
        $archiveHandle = fopen($archivePath, 'rb');
        if ($archiveHandle === false) {
            throw new Exception("Can’t open archive at $archivePath!", self::EXCEPTION_CODE_OPEN_ERROR);
        }
        $archiveHeader = (new DupArchiveHeader())->readFromArchive($archiveHandle, $password);
        $writeInfo     = new DupArchiveExpanderInfo();
        $writeInfo->destDirectory = $destDirectory;
        if ($offset > 0) {
            fseek($archiveHandle, $offset);
        }
        $moreToRead = true;
        while ($moreToRead) {
            if ($writeInfo->currentFileHeader != null) {
                try {
                    if (self::passesInclusionFilter($inclusionFilter, $writeInfo->currentFileHeader->relativePath)) {
                        self::writeToFile($archiveHandle, $writeInfo);
                        $writeInfo->fileWriteCount++;
                    } elseif ($writeInfo->currentFileHeader->fileSize > 0) {
                        self::skipFileInArchive($archiveHandle, $writeInfo->currentFileHeader);
                    }
                    $writeInfo->currentFileHeader = null;
                } catch (Exception $ex) {
                    if (!$ignoreErrors) {
                        throw $ex;
                    }
                }
            } else {
                $headerType = self::getNextHeaderType($archiveHandle);
                switch ($headerType) {
                    case self::HEADER_TYPE_FILE:
                        $writeInfo->currentFileHeader = (new DupArchiveFileHeader($archiveHeader))->readFromArchive($archiveHandle, false, true);
                        break;
                    case self::HEADER_TYPE_DIR:
                        $directoryHeader = (new DupArchiveDirectoryHeader($archiveHeader))->readFromArchive($archiveHandle, true);
                        if (self::passesInclusionFilter($inclusionFilter, $directoryHeader->relativePath)) {
                            $directory = "{$writeInfo->destDirectory}/{$directoryHeader->relativePath}";
                            if (is_callable(self::$mkdirCallback)) {
                                call_user_func(self::$mkdirCallback, $directory, 'u+rwx', true);
                            } else {
                                mkdir($directory, 0755, true);
                            }
                            $writeInfo->directoryWriteCount++;
                        } else {
                        }
                        break;
                    case self::HEADER_TYPE_NONE:
                        $moreToRead = false;
                }
            }
        }
        fclose($archiveHandle);
    }
    private static function writeToFile($archiveHandle, DupArchiveExpanderInfo $writeInfo)
    {
        $destFilePath = $writeInfo->getCurrentDestFilePath();
        if ($writeInfo->currentFileHeader->fileSize > 0) {
            $parentDir = dirname($destFilePath);
            if (!file_exists($parentDir)) {
                if (is_callable(self::$mkdirCallback)) {
                    $res = call_user_func(self::$mkdirCallback, $parentDir, 'u+rwx', true);
                } else {
                    $res = mkdir($parentDir, 0755, true);
                }
                if (!$res) {
                    throw new Exception("Couldn't create {$parentDir}", self::EXCEPTION_CODE_EXTRACT_ERROR);
                }
            }
            $destFileHandle = fopen($destFilePath, 'wb+');
            if ($destFileHandle === false) {
                throw new Exception("Couldn't open {$destFilePath} for writing.", self::EXCEPTION_CODE_OPEN_ERROR);
            }
            do {
                self::appendGlobToFile($archiveHandle, $destFileHandle, $writeInfo);
                $currentFileOffset = ftell($destFileHandle);
                $moreGlobstoProcess = $currentFileOffset < $writeInfo->currentFileHeader->fileSize;
            } while ($moreGlobstoProcess);
            fclose($destFileHandle);
            if (is_callable(self::$chmodCallback)) {
                call_user_func(self::$chmodCallback, $destFilePath, 'u+rw');
            } else {
                chmod($destFilePath, 0644);
            }
            if ($writeInfo->currentFileHeader->validateFile($destFilePath) == false) {
                throw new Exception("HASH Validation fails for {$destFilePath}", self::EXCEPTION_CODE_VALIDATION_ERROR);
            }
        } else {
            if (touch($destFilePath) === false) {
                throw new Exception("Couldn't create $destFilePath", self::EXCEPTION_CODE_EXTRACT_ERROR);
            }
            if (is_callable(self::$chmodCallback)) {
                call_user_func(self::$chmodCallback, $destFilePath, 'u+rw');
            } else {
                chmod($destFilePath, 0644);
            }
        }
    }
    private static function appendGlobToFile($archiveHandle, $destFileHandle, DupArchiveExpanderInfo $writeInfo)
    {
        $globHeader  = (new DupArchiveGlobHeader($writeInfo->currentFileHeader))->readFromArchive($archiveHandle);
        $globContent = $globHeader->readContent($archiveHandle);
        if (fwrite($destFileHandle, $globContent) !== strlen($globContent)) {
            throw new Exception("Unable to write all bytes of data glob to storage.", self::EXCEPTION_CODE_EXTRACT_ERROR);
        }
    }
    private static function passesInclusionFilter($filter, $candidate)
    {
        return (substr($candidate, 0, strlen($filter)) == $filter);
    }
}
}
namespace Duplicator\Libs\DupArchive\Headers {
use Duplicator\Libs\DupArchive\DupArchive;
use Exception;
abstract class AbstractDupArchiveHeader
{
    const MAX_FILED_LEN = 1024;
    abstract public function readFromArchive($archiveHandle);
    abstract public function writeToArchive($archiveHandle);
    public static function getHeaderField($archiveHandle, $ename)
    {
        $expectedStart = '<' . $ename . '>';
        $expectedEnd   = '</' . $ename . '>';
        $startingElement = fread($archiveHandle, strlen($expectedStart));
        if ($startingElement !== $expectedStart) {
            throw new Exception(
                "Invalid starting element. Was expecting {$expectedStart} but got {$startingElement}",
                DupArchive::EXCEPTION_CODE_INVALID_MARKER
            );
        }
        $headerString = stream_get_line($archiveHandle, self::MAX_FILED_LEN, $expectedEnd);
        if ($headerString === false) {
            throw new Exception('Error reading line.', DupArchive::EXCEPTION_CODE_EXTRACT_ERROR);
        }
        return $headerString;
    }
}
}
namespace Duplicator\Libs\DupArchive\Headers {
use Duplicator\Libs\DupArchive\DupArchive;
use Exception;
class DupArchiveDirectoryHeader extends AbstractDupArchiveHeader
{
    public $mtime              = 0;
    public $permissions        = '';
    public $relativePathLength = 1;
    public $relativePath       = '';
    public function __construct(DupArchiveHeader $archiveHeader) // @phpstan-ignore-line
    {
    }
    public function readFromArchive($archiveHandle, $skipStartElement = false)
    {
        if (!$skipStartElement) {
            $startElement = fread($archiveHandle, 3);
            if ($startElement === false) {
                if (feof($archiveHandle)) {
                    return false;
                } else {
                    throw new Exception('Error reading directory header', DupArchive::EXCEPTION_CODE_INVALID_MARKER);
                }
            }
            if ($startElement != '<D>') {
                throw new Exception(
                    "Invalid directory header marker found [{$startElement}] : location " . ftell($archiveHandle),
                    DupArchive::EXCEPTION_CODE_EXTRACT_ERROR
                );
            }
        }
        $this->mtime              = self::getHeaderField($archiveHandle, 'MT');
        $this->permissions        = self::getHeaderField($archiveHandle, 'P');
        $this->relativePathLength = self::getHeaderField($archiveHandle, 'RPL');
        fread($archiveHandle, 4);
        $this->relativePath = fread($archiveHandle, $this->relativePathLength);
        fread($archiveHandle, 9);
        return $this;
    }
    public function writeToArchive($archiveHandle)
    {
        if ($this->relativePathLength == 0) {
            return;
        }
        $headerString = '<D><MT>' .
            $this->mtime . '</MT><P>' .
            $this->permissions . '</P><RPL>' .
            $this->relativePathLength . '</RPL><RP>' .
            $this->relativePath . '</RP></D>';
        $bytes_written = @fwrite($archiveHandle, $headerString);
        if ($bytes_written === false) {
            throw new Exception('Error writing to file.', DupArchive::EXCEPTION_CODE_ADD_ERROR);
        } else {
            return $bytes_written;
        }
    }
}
}
namespace Duplicator\Libs\DupArchive\Headers {
use Duplicator\Libs\DupArchive\DupArchive;
use Duplicator\Libs\Snap\SnapIO;
use Exception;
class DupArchiveFileHeader extends AbstractDupArchiveHeader
{
    const MAX_SIZE_FOR_HASHING = 1000000000;
    public $fileSize           = 0;
    public $mtime              = 0;
    public $permissions        = '';
    public $hash               = '';
    public $relativePathLength = 0;
    public $relativePath       = '';
    protected $flags           = 0;
    protected $version         = '';
    protected $password        = '';
    public function __construct(DupArchiveHeader $archiveHeader)
    {
        $this->flags    = $archiveHeader->getFlags();
        $this->version  = $archiveHeader->getVersion();
        $this->password = $archiveHeader->getPassword();
    }
    public function getVersion()
    {
        return $this->version;
    }
    public function getFlags()
    {
        return $this->flags;
    }
    public function setFlags($flags)
    {
        $this->flags = (int) $flags;
    }
    public function isCompressed()
    {
        return ($this->flags & DupArchive::FLAG_COMPRESS ? true : false);
    }
    public function isCrypt()
    {
        return ($this->flags & DupArchive::FLAG_CRYPT ? true : false);
    }
    public function getPassword()
    {
        return $this->password;
    }
    public function createFromFile($filepath, $relativeFilePath)
    {
        $this->fileSize    = SnapIO::filesize($filepath);
        $this->permissions = substr(sprintf('%o', fileperms($filepath)), -4);
        $this->mtime       = SnapIO::filemtime($filepath);
        if ($this->fileSize > self::MAX_SIZE_FOR_HASHING) {
            $this->hash = "00000000000000000000000000000000";
        } else {
            $this->hash = hash_file(DupArchive::HASH_ALGO, $filepath);
        }
        $this->relativePath       = $relativeFilePath;
        $this->relativePathLength = strlen($this->relativePath);
        return $this;
    }
    public function createFromSrc($src, $relativeFilePath, $forceSize = 0)
    {
        $this->fileSize    = strlen($src);
        $this->permissions = '0644';
        $this->mtime       = time();
        $srcLen = strlen($src);
        if ($forceSize > 0 && $srcLen < $forceSize) {
            $charsToAdd = $forceSize - $srcLen;
            $src       .= str_repeat("\0", $charsToAdd);
        }
        if ($this->fileSize > self::MAX_SIZE_FOR_HASHING) {
            $this->hash = "00000000000000000000000000000000";
        } else {
            $this->hash = hash(DupArchive::HASH_ALGO, $src);
        }
        $this->relativePath       = $relativeFilePath;
        $this->relativePathLength = strlen($this->relativePath);
        return $this;
    }
    public function validateFile($filePath)
    {
        if ($this->hash === '00000000000000000000000000000000') {
            return true;
        }
        $hash = hash_file(DupArchive::HASH_ALGO, $filePath);
        return ($hash === $this->hash);
    }
    public function writeToArchive($archiveHandle)
    {
        $headerString  = '<F>';
        $headerString .= '<FS>' . $this->fileSize . '</FS>';
        $headerString .= '<MT>' . $this->mtime . '</MT>';
        $headerString .= '<P>' . $this->permissions . '</P>';
        $headerString .= '<X>' . pack('v', $this->flags) . '</X>';
        $headerString .= '<HA>' . $this->hash . '</HA>';
        $headerString .= '<RPL>' . $this->relativePathLength . '</RPL>';
        $headerString .= '<RP>' . $this->relativePath . '</RP>';
        $headerString .= '</F>';
        $bytes_written = @fwrite($archiveHandle, $headerString);
        if ($bytes_written === false) {
            throw new Exception('Error writing to file.', DupArchive::EXCEPTION_CODE_EXTRACT_ERROR);
        } else {
            return $bytes_written;
        }
    }
    public function readFromArchive($archiveHandle, $skipContents = false, $skipMarker = false)
    {
        if (!$skipMarker) {
            $marker = @fread($archiveHandle, 3);
            if ($marker === false) {
                if (feof($archiveHandle)) {
                    return false;
                } else {
                    throw new Exception('Error reading file header', DupArchive::EXCEPTION_CODE_EXTRACT_ERROR);
                }
            }
            if ($marker != '<F>') {
                throw new Exception(
                    "Invalid file header marker found [{$marker}] : location " . ftell($archiveHandle),
                    DupArchive::EXCEPTION_CODE_INVALID_MARKER
                );
            }
        }
        $this->fileSize    = self::getHeaderField($archiveHandle, 'FS');
        $this->mtime       = self::getHeaderField($archiveHandle, 'MT');
        $this->permissions = self::getHeaderField($archiveHandle, 'P');
        if (version_compare($this->version, '5.0.0', '<')) {
        } else {
            $falgs       = self::getHeaderField($archiveHandle, 'X');
            $this->flags = unpack('vflags', $falgs)['flags'];
        }
        $this->hash               = self::getHeaderField($archiveHandle, 'HA');
        $this->relativePathLength = self::getHeaderField($archiveHandle, 'RPL');
        fread($archiveHandle, 4);
        $this->relativePath = fread($archiveHandle, $this->relativePathLength);
        fread($archiveHandle, 9);
        if ($skipContents && ($this->fileSize > 0)) {
            $dataSize   = 0;
            $moreGlobs  = true;
            $globHeader = new DupArchiveGlobHeader($this);
            while ($moreGlobs) {
                $globHeader->readFromArchive($archiveHandle, true);
                $dataSize += $globHeader->originalSize;
                $moreGlobs = ($dataSize < $this->fileSize);
            }
        }
        return $this;
    }
}
}
namespace Duplicator\Libs\DupArchive\Headers {
use Duplicator\Libs\DupArchive\DupArchive;
use Exception;
class DupArchiveGlobHeader extends AbstractDupArchiveHeader
{
    public $originalSize = 0;
    public $storedSize   = 0;
    protected $hash      = '';
    protected $fileHeader =  null;
    public function __construct(DupArchiveFileHeader $fileHeader)
    {
        $this->fileHeader = $fileHeader;
    }
    public function setHash($content)
    {
        $this->hash = hash(DupArchive::HASH_ALGO, $content);
    }
    public function checkHash($content, $storedContent)
    {
        if (version_compare($this->fileHeader->getVersion(), '5.0.0', '<')) {
            $hash = hash(DupArchive::HASH_ALGO, $storedContent);
        } else {
            $hash = hash(DupArchive::HASH_ALGO, $content);
        }
        return ($hash === $this->hash);
    }
    public function writeToArchive($archiveHandle)
    {
        $headerString = '<G><OS>' . $this->originalSize . '</OS><SS>' . $this->storedSize . '</SS><HA>' . $this->hash . '</HA></G>';
        $bytes_written = @fwrite($archiveHandle, $headerString);
        if ($bytes_written === false) {
            throw new Exception('Error writing to file.');
        } else {
            return $bytes_written;
        }
    }
    public function readFromArchive($archiveHandle, $skipGlob = false)
    {
        $startElement = fread($archiveHandle, 3);
        if ($startElement !== '<G>') {
            throw new Exception("Invalid glob header marker found {$startElement}. location:" . ftell($archiveHandle));
        }
        $this->originalSize = self::getHeaderField($archiveHandle, 'OS');
        $this->storedSize   = self::getHeaderField($archiveHandle, 'SS');
        $this->hash         = self::getHeaderField($archiveHandle, 'HA');
        fread($archiveHandle, 4);
        if ($skipGlob) {
            if (fseek($archiveHandle, $this->storedSize, SEEK_CUR) === -1) {
                throw new Exception("Can't fseek when skipping glob at location:" . ftell($archiveHandle));
            }
        }
        return $this;
    }
    public function getContentToWrite($content)
    {
        if ($this->fileHeader->isCompressed()) {
            if (($content = gzdeflate($content, 2)) === false) {
                throw new Exception("Error gzdeflate glob content", DupArchive::EXCEPTION_CODE_EXTRACT_ERROR);
            }
        }
        if ($this->fileHeader->isCrypt()) {
            if (($content = DupArchive::encrypt($content, $this->fileHeader->getPassword())) === false) {
                throw new Exception("Error encrypt glob content", DupArchive::EXCEPTION_CODE_EXTRACT_ERROR);
            }
        }
        return $content;
    }
    public function readContent($archiveHandle, $validate = false)
    {
        if ($this->storedSize == 0) {
            return 0;
        }
        if (($content = fread($archiveHandle, $this->storedSize)) === false) {
            throw new Exception("Error reading glob content");
        }
        $result = $content;
        if ($this->fileHeader->isCrypt()) {
            if (($result = DupArchive::decrypt($result, $this->fileHeader->getPassword())) === false) {
                throw new Exception("Error decrypt glob content");
            }
        }
        if ($this->fileHeader->isCompressed()) {
            if (($result = gzinflate($result)) === false) {
                throw new Exception("Error gzinflate glob content");
            }
        }
        if ($validate && $this->checkHash($result, $content) == false) {
            throw new Exception('Glob validation fail');
        }
        return  $result;
    }
}
}
namespace Duplicator\Libs\DupArchive\Headers {
use Duplicator\Libs\DupArchive\DupArchive;
use Duplicator\Libs\Snap\SnapIO;
use Exception;
class DupArchiveHeader extends AbstractDupArchiveHeader
{
    protected $version = '';
    protected $flags = 0;
    protected $password = '';
    protected $hashPassword = '';
    public function __construct($flags = 0, $verison = '')
    {
        $this->version = (strlen($verison) ? $verison : DupArchive::DUPARCHIVE_VERSION);
        $this->flags   = (int) $flags;
    }
    public function getVersion()
    {
        return $this->version;
    }
    public function getFlags()
    {
        return $this->flags;
    }
    public function setFlags($flags)
    {
        $this->flags = (int) $flags;
    }
    public function setPassword($pwd)
    {
        if (strlen($pwd) == 0) {
            $this->flags       &= ~DupArchive::FLAG_CRYPT;
            $this->password     = '';
            $this->hashPassword = '';
        } else {
            $this->flags        = $this->flags | DupArchive::FLAG_CRYPT;
            $this->password     = $pwd;
            $this->hashPassword = self::pwdToHash($pwd, DupArchive::generateSalt(16));
        }
    }
    public function getPassword()
    {
        return $this->password;
    }
    public function writeToArchive($archiveHandle)
    {
        $content  = '<A>';
        $content .= '<V>' . $this->version . '</V>';
        $content .= '<X>' . pack('v', $this->flags) . '</X>';
        $content .= '<P>' . $this->hashPassword . '</P>';
        $content .= '</A>';
        return SnapIO::fwrite($archiveHandle, $content);
    }
    public function isCompressed()
    {
        return ($this->flags & DupArchive::FLAG_COMPRESS ? true : false);
    }
    public function isCrypt()
    {
        return ($this->flags & DupArchive::FLAG_CRYPT ? true : false);
    }
    public function readFromArchive($archiveHandle, $password = '')
    {
        $startElement = fgets($archiveHandle, 4);
        if ($startElement != '<A>') {
            throw new Exception("Invalid archive header marker found {$startElement}", DupArchive::EXCEPTION_CODE_INVALID_MARKER);
        }
        $version      = self::getHeaderField($archiveHandle, 'V');
        $flags        = 0;
        $hashPassword = '';
        if (version_compare($version, '5.0.0', '<')) {
            if (filter_var(self::getHeaderField($archiveHandle, 'C'), FILTER_VALIDATE_BOOLEAN)) {
                $flags = DupArchive::FLAG_COMPRESS;
            }
            $password = '';
        } else {
            $falgs        = self::getHeaderField($archiveHandle, 'X');
            $flags        = unpack('vflags', $falgs)['flags'];
            $hashPassword = self::getHeaderField($archiveHandle, 'P');
        }
        if (strlen($hashPassword)) {
            if (preg_match('/^\$(\d)\$rounds=(\d+)\$(.+)\$(.+)$/', $hashPassword, $matches) !== 1) {
                throw new Exception("Invalid archive stored password", DupArchive::EXCEPTION_CODE_EXTRACT_ERROR);
            }
            $algo   = $matches[1];
            $rounds = $matches[2];
            $salt   = $matches[3];
            $hash   = $matches[4];
            if (!hash_equals($hashPassword, self::pwdToHash($password, $salt))) {
                throw new Exception("Invalid archive password", DupArchive::EXCEPTION_CODE_INVALID_PASSWORD);
            }
        }
        $this->flags        = $flags;
        $this->version      = $version;
        $this->password     = $password;
        $this->hashPassword = $hashPassword;
        fgets($archiveHandle, 5);
        return $this;
    }
    protected function pwdToHash($password, $salt)
    {
        return crypt($password, DupArchive::PWD_ALGO . $salt . '$');
    }
}
}
namespace Duplicator\Libs\DupArchive\Info {
use Duplicator\Libs\DupArchive\Headers\DupArchiveFileHeader;
class DupArchiveExpanderInfo
{
    public $archiveHandle = null;
    public $currentFileHeader   = null;
    public $destDirectory       = null;
    public $directoryWriteCount = 0;
    public $fileWriteCount      = 0;
    public $enableWrite         = false;
    public function getCurrentDestFilePath()
    {
        if ($this->destDirectory != null) {
            return "{$this->destDirectory}/{$this->currentFileHeader->relativePath}";
        } else {
            return null;
        }
    }
}
}

namespace {
    use Duplicator\Installer\Bootstrap\BootstrapRunner;
    use Duplicator\Installer\Bootstrap\BootstrapUtils;
    use Duplicator\Installer\Bootstrap\BootstrapView;
    use Duplicator\Installer\Bootstrap\LogHandler;
    class InstallerBootstrapData {
        const ARCHIVE_FILENAME       = '1034am9august_b51a59f829c31b793456_20240809053452_archive.daf';
        const ARCHIVE_SIZE           = '163136961';
        const INSTALLER_DIR_NAME     = 'dup-installer';
        const PACKAGE_HASH           = 'b51a59f-09053452';
        const SECONDARY_PACKAGE_HASH = 'f3bd7fb-09053452';
        const VERSION                = '4.5.9.1';
    }
    BootstrapUtils::phpVersionCheck(BootstrapRunner::MINIMUM_PHP_VERSION);
    BootstrapRunner::initSetValues();
    $bootError = null;
    $view = '';
    try {
        $boot = BootstrapRunner::getInstance();
        LogHandler::initErrorHandler(array($boot, 'log'));
        $bootView = new BootstrapView();
        $view = $boot->run();
    } catch (Exception $e) {
        $boot->log("[ERROR] Boot msg:" . $e->getMessage() . "\n" . $e->getTraceAsString());
        $boot->appendErrorMessage($e->getMessage());
        $view  = BootstrapView::VIEW_ERROR;
    }
    switch ($view) {
        case BootstrapView::VIEW_REDIRECT:
            $bootView->redirectToInsaller();
            break;
        case BootstrapView::VIEW_ERROR:
            $bootView->renderError();
            break;
        case BootstrapView::VIEW_PASSWORD:
            $bootView->renderPassword();
            break;
    }
}
/* DUPLICATOR_PRO_INSTALLER_EOF */
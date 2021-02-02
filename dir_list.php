<?php
class DirectoryListing {
	
	// The top level directory where this script is located, or alternatively one of it's sub-directories
	public $startDirectory = '.';

	// An optional title to show in the address bar and at the top of your page (set to null to leave blank)
	public $pageTitle = 'Listing of files in directory';

	// The URL of this script. Optionally set if your server is unable to detect the paths of files
	public $includeUrl = false;

	// Set to true to list all sub-directories and allow them to be browsed
	public $showSubDirectories = true;

	// Set to true to open all file links in a new browser tab
	public $openLinksInNewTab = true;

	// Set to true to show thumbnail previews of any images
	public $showThumbnails = true;

	// Set to true to allow new directories to be created.
	public $enableDirectoryCreation = true;

	// Set to true to allow file uploads (NOTE: you should set a password if you enable this!)
	public $enableUploads = true;

	// Enable multi-file uploads (NOTE: This makes use of javascript libraries hosted by Google so an internet connection is required.)
	public $enableMultiFileUploads = true;

	// Set to true to overwrite files on the server if they have the same name as a file being uploaded
	public $overwriteOnUpload = false;

	// Set to true to enable file deletion options
	public $enableFileDeletion = true;

	// Set to true to enable directory deletion options (only available when the directory is empty)
	public $enableDirectoryDeletion = true;

	// List of all mime types that can be uploaded. Full list of mime types: http://www.iana.org/assignments/media-types/media-types.xhtml
	public $allowedUploadMimeTypes = array(
		'image/jpeg',
		'image/gif',
		'image/png',
		'text/plain',
		'text/html',
		'text/css',
		'text/csv',
		'application/pdf',
		'application/x-pdf',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // MS Word (modern)
		'application/msword',
		'application/vnd.ms-excel',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // MS Excel (modern)
	);

	// Set to true to unzip any zip files that are uploaded (note - will overwrite files of the same name!)
	public $enableUnzipping = true;

	// If you've enabled unzipping, you can optionally delete the original zip file after its uploaded by setting this to true.
	public $deleteZipAfterUploading = false;

	// The Evoluted Directory Listing Script uses Bootstrap. By setting this value to true, a nicer theme will be loaded remotely.
	// Setting this to false will make the directory listing script use the default bootstrap style, loaded locally.
	public $enableTheme = true;

	// Set to true to require a password be entered before being able to use the script
	public $passwordProtect = false;

	// The password to require to use this script (only used if $passwordProtect is set to true)
	public $password = 'password';

	// Optional. Allow restricted access only to whitelisted IP addresses
	public $enableIpWhitelist = false;

	// List of IP's to allow access to the script (only used if $enableIpWhitelist is true)
	public $ipWhitelist = array(
		'127.0.0.1'
	);

	// File extensions to block from showing in the directory listing
	public $ignoredFileExtensions = array(
		'php',
		'ini',
	);

	// File names to block from showing in the directory listing
	public $ignoredFileNames = array(
		'.htaccess',
		'.DS_Store',
		'Thumbs.db',
	);

	// Directories to block from showing in the directory listing
	public $ignoredDirectories = array(

	);

	// Files that begin with a dot are usually hidden files. Set this to false if you wish to show these hiden files.
	public $ignoreDotFiles = true;

	// Works the same way as $ignoreDotFiles but with directories.
	public $ignoreDotDirectories = true;

	/*
	====================================================================================================
	You shouldn't need to edit anything below this line unless you wish to add functionality to the
	script. You should only edit this area if you know what you are doing!
	====================================================================================================
	*/
	private $__previewMimeTypes = array(
		'image/gif',
		'image/jpeg',
		'image/png',
	);

	private $__currentDirectory = null;

	private $__fileList = array();

	private $__directoryList = array();

	private $__debug = true;

	public $sortBy = 'modified';

	public $sortableFields = array(
		'name',
		'size',
		'modified'
	);

	private $__sortOrder = 'desc';

	public function __construct() {
		define('DS', '/');
	}

	public function list() {
		$this->__currentDirectory = $this->startDirectory;

		// Sorting
		if (isset($_GET['order']) && in_array($_GET['order'], $this->sortableFields)) {
			$this->sortBy = $_GET['order'];
		}

		if (isset($_GET['sort']) && ($_GET['sort'] == 'asc' || $_GET['sort'] == 'desc')) {
			$this->__sortOrder = $_GET['sort'];
		}

		if (isset($_GET['dir'])) {
			if (isset($_GET['delete']) && $this->enableDirectoryDeletion) {
				$this->deleteDirectory();
			}

			$this->__currentDirectory = $_GET['dir'];
			return $this->__display();
		} elseif (isset($_GET['preview'])) {
			$this->__generatePreview($_GET['preview']);
		} else {
			return $this->__display();
		}
	}

	
	public function upload() {
		$files = $this->__formatUploadArray($_FILES['upload']);

		if ($this->enableUploads) {
			if ($this->enableMultiFileUploads) {
				foreach ($files as $file) {
					$status = $this->__processUpload($file);
				}
			} else {
				$file = $files[0];
				$status = $this->__processUpload($file);
			}

			return $status;
		}
		return false;
	}

	private function __formatUploadArray($files) {
		$fileAry = array();
		$fileCount = count($files['name']);
		$fileKeys = array_keys($files);

		for ($i = 0; $i < $fileCount; $i++) {
			foreach ($fileKeys as $key) {
				$fileAry[$i][$key] = $files[$key][$i];
			}
		}

		return $fileAry;
	}

	private function __processUpload($file) {
		if (isset($_GET['dir'])) {
			$this->__currentDirectory = $_GET['dir'];
		}

		if (! $this->__currentDirectory) {
			$filePath = realpath($this->startDirectory);
		} else {
			$this->__currentDirectory = str_replace('..', '', $this->__currentDirectory);
			$this->__currentDirectory = ltrim($this->__currentDirectory, "/");
			$filePath = realpath($this->__currentDirectory);
		}

		$filePath = $filePath . DS . $file['name'];

		if (! empty($file)) {

			if (! $this->overwriteOnUpload) {
				if (file_exists($filePath)) {
					return 2;
				}
			}

			if (! in_array(mime_content_type($file['tmp_name']), $this->allowedUploadMimeTypes)) {
				return 3;
			}

			move_uploaded_file($file['tmp_name'], $filePath);

			return true;
		}
	}

	public function deleteFile() {
		if (isset($_GET['deleteFile'])) {
			$file = $_GET['deleteFile'];

			// Clean file path
			$file = str_replace('..', '', $file);
			$file = ltrim($file, "/");

			// Work out full file path
			$filePath = __DIR__ . $this->__currentDirectory . '/' . $file;

			if (file_exists($filePath) && is_file($filePath)) {
				return unlink($filePath);
			}
			return false;
		}
	}

	public function deleteDirectory() {
		if (isset($_GET['dir'])) {
			$dir = $_GET['dir'];
			// Clean dir path
			$dir = str_replace('..', '', $dir);
			$dir = ltrim($dir, "/");

			// Work out full directory path
			$dirPath = __DIR__ . '/' . $dir;

			if (file_exists($dirPath) && is_dir($dirPath)) {

				$iterator = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
				$files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);

				foreach ($files as $file) {
					if ($file->isDir()) {
						rmdir($file->getRealPath());
					} else {
						unlink($file->getRealPath());
					}
				}
				return rmdir($dir);
			}
		}
		return false;
	}

	
	public function sortUrl($sort) {

		// Get current URL parts
		$urlParts = parse_url($_SERVER['REQUEST_URI']);

		$url = '';

		if (isset($urlParts['scheme'])) {
			$url = $urlParts['scheme'] . '://';
		}

		if (isset($urlParts['host'])) {
			$url .= $urlParts['host'];
		}

		if (isset($urlParts['path'])) {
			$url .= $urlParts['path'];
		}


		// Extract query string
		if (isset($urlParts['query'])) {
			$queryString = $urlParts['query'];

			parse_str($queryString, $queryParts);

			// work out if we're already sorting by the current heading
			if (isset($queryParts['order']) && $queryParts['order'] == $sort) {
				// Yes we are, just switch the sort option!
				if (isset($queryParts['sort'])) {
					if ($queryParts['sort'] == 'asc') {
						$queryParts['sort'] = 'desc';
					} else {
						$queryParts['sort'] = 'asc';
					}
				}
			} else {
				$queryParts['order'] = $sort;
				$queryParts['sort'] = 'asc';
			}

			// Now convert back to a string
			$queryString = http_build_query($queryParts);

			$url .= '?' . $queryString;
		} else {
			$order = 'asc';
			if ($sort == $this->sortBy) {
				$order = 'desc';
			}
			$queryString = 'order=' . $sort . '&sort=' . $order;
			$url .= '?' . $queryString;
		}

		return $url;
	}

	public function sortClass($sort) {
		$class = $sort . '_';

		if ($this->sortBy == $sort) {
			if ($this->__sortOrder == 'desc') {
				$class .= 'desc sort_desc';
			} else {
				$class .= 'asc sort_asc';
			}
		} else {
			$class = '';
		}
		return $class;
	}

	private function __ipWhitelistCheck() {
		// Get the users ip
		$userIp = $_SERVER['REMOTE_ADDR'];

		if (! in_array($userIp, $this->ipWhitelist)) {
			header('HTTP/1.0 403 Forbidden');
			die('Your IP address (' . $userIp . ') is not authorized to access this file.');
		}
	}

	private function __display() {
		if ($this->__currentDirectory != '.' && !$this->__endsWith($this->__currentDirectory, DS)) {
			$this->__currentDirectory = $this->__currentDirectory . DS;
		}

		return $this->__loadDirectory($this->__currentDirectory);
	}

	private function __loadDirectory($path) {
		$files = $this->__scanDir($path);

		if (! empty($files)) {
			// Strip excludes files, directories and filetypes
			$files = $this->__cleanFileList($files);
			foreach ($files as $file) {
				$filePath = realpath($this->__currentDirectory . DS . $file);

				if ($this->__isDirectory($filePath)) {

					if (! $this->includeUrl) {
						$urlParts = parse_url($_SERVER['REQUEST_URI']);

						$dirUrl = '';

						if (isset($urlParts['scheme'])) {
							$dirUrl = $urlParts['scheme'] . '://';
						}

						if (isset($urlParts['host'])) {
							$dirUrl .= $urlParts['host'];
						}

						if (isset($urlParts['path'])) {
							$dirUrl .= $urlParts['path'];
						}
					} else {
						$dirUrl = $this->directoryUrl;
					}

					if ($this->__currentDirectory != '' && $this->__currentDirectory != '.') {
						$dirUrl .= '?dir=' . rawurlencode($this->__currentDirectory) . rawurlencode($file);
					} else {
						$dirUrl .= '?dir=' . rawurlencode($file);
					}

					$this->__directoryList[$file] = array(
						'name' => rawurldecode($file),
						'path' => $filePath,
						'type' => 'dir',
						'url' => $dirUrl
					);
				} else {
					$this->__fileList[$file] = $this->__getFileType($filePath, $this->__currentDirectory . DS . $file);
				}
			}
		}

		if (! $this->showSubDirectories) {
			$this->__directoryList = null;
		}

		$data = array(
			'currentPath' => $this->__currentDirectory,
			'directoryTree' => $this->__getDirectoryTree(),
			'files' => $this->__setSorting($this->__fileList),
			'directories' => $this->__directoryList,
			'requirePassword' => $this->passwordProtect,
			'enableUploads' => $this->enableUploads
		);

		return $data;
	}

	private function __setSorting($data) {
		$sortOrder = '';
		$sortBy = '';

		// Sort the files
		if ($this->sortBy == 'name') {
			function compareByName($a, $b) {
				return strnatcasecmp($a['name'], $b['name']);
			}

			usort($data, 'compareByName');
			$this->soryBy = 'name';
		} elseif ($this->sortBy == 'size') {
			function compareBySize($a, $b) {
				return strnatcasecmp($a['size_bytes'], $b['size_bytes']);
			}

			usort($data, 'compareBySize');
			$this->soryBy = 'size';
		} elseif ($this->sortBy == 'modified') {
			function compareByModified($a, $b) {
				return strnatcasecmp($a['modified'], $b['modified']);
			}

			usort($data, 'compareByModified');
			$this->soryBy = 'modified';
		}

		if ($this->__sortOrder == 'desc') {
			$data = array_reverse($data);
		}
		return $data;
	}

	private function __scanDir($dir) {
		// Prevent browsing up the directory path.
		if (strstr($dir, '../')) {
			return false;
		}

		if ($dir == '/') {
			$dir = $this->startDirectory;
			$this->__currentDirectory = $dir;
		}

		$strippedDir = str_replace('/', '', $dir);

		$dir = ltrim($dir, "/");

		// Prevent listing blacklisted directories
		if (in_array($strippedDir, $this->ignoredDirectories)) {
			return false;
		}

		if (! file_exists($dir) || !is_dir($dir)) {
			return false;
		}

		return scandir($dir);
	}

	private function __cleanFileList($files) {
		$this->ignoredDirectories[] = '.';
		$this->ignoredDirectories[] = '..';
		foreach ($files as $key => $file) {

			// Remove unwanted directories
			if ($this->__isDirectory(realpath($file)) && in_array($file, $this->ignoredDirectories)) {
				unset($files[$key]);
			}

			// Remove dot directories (if enables)
			if ($this->ignoreDotDirectories && substr($file, 0, 1) === '.') {
				unset($files[$key]);
			}

			// Remove unwanted files
			if (! $this->__isDirectory(realpath($file)) && in_array($file, $this->ignoredFileNames)) {
				unset($files[$key]);
			}
			// Remove unwanted file extensions
			if (! $this->__isDirectory(realpath($file))) {

				$info = pathinfo(mb_convert_encoding($file, 'UTF-8', 'UTF-8'));

				if (isset($info['extension'])) {
					$extension = $info['extension'];

					if (in_array($extension, $this->ignoredFileExtensions)) {
						unset($files[$key]);
					}
				}

				// If dot files want ignoring, do that next
				if ($this->ignoreDotFiles) {

					if (substr($file, 0, 1) == '.') {
						unset($files[$key]);
					}
				}
			}
		}
		return $files;
	}

	private function __isDirectory($file) {
		if ($file == $this->__currentDirectory . DS . '.' || $file == $this->__currentDirectory . DS . '..') {
			return true;
		}
		$file = mb_convert_encoding($file, 'UTF-8', 'UTF-8');

		if (filetype($file) == 'dir') {
			return true;
		}

		return false;
	}

	/**
	 * __getFileType
	 *
	 * Returns the formatted array of file data used for thre directory listing.
	 *
	 * @param  string $filePath Full path to the file
	 * @return array   Array of data for the file
	 */
	private function __getFileType($filePath, $relativePath = null) {
		$fi = new finfo(FILEINFO_MIME_TYPE);

		if (! file_exists($filePath)) {
			return false;
		}

		$type = $fi->file($filePath);

		$filePathInfo = pathinfo($filePath);

		$fileSize = filesize($filePath);

		$fileModified = filemtime($filePath);

		$filePreview = false;

		// Check if the file type supports previews
		if ($this->__supportsPreviews($type) && $this->showThumbnails) {
			$filePreview = true;
		}

		return array(
			'name' => $filePathInfo['basename'],
			'extension' => (isset($filePathInfo['extension']) ? $filePathInfo['extension'] : null),
			'dir' => $filePathInfo['dirname'],
			'path' => $filePath,
			'relativePath' => $relativePath,
			'size' => $this->__formatSize($fileSize),
			'size_bytes' => $fileSize,
			'modified' => $fileModified,
			'type' => 'file',
			'mime' => $type,
			'url' => $this->__getUrl($filePathInfo['basename']),
			'preview' => $filePreview,
			'target' => ($this->openLinksInNewTab ? '_blank' : '_parent')
		);
	}

	private function __supportsPreviews($type) {
		if (in_array($type, $this->__previewMimeTypes)) {
			return true;
		}
		return false;
	}

	/**
	 * __getUrl
	 *
	 * Returns the url to the file.
	 *
	 * @param  string $file filename
	 * @return string   url of the file
	 */
	private function __getUrl($file) {
		if (! $this->includeUrl) {
			$dirUrl = $_SERVER['REQUEST_URI'];

			$urlParts = parse_url($_SERVER['REQUEST_URI']);

			$dirUrl = '';

			if (isset($urlParts['scheme'])) {
				$dirUrl = $urlParts['scheme'] . '://';
			}

			if (isset($urlParts['host'])) {
				$dirUrl .= $urlParts['host'];
			}

			if (isset($urlParts['path'])) {
				$dirUrl .= $urlParts['path'];
			}
		} else {
			$dirUrl = $this->directoryUrl;
		}

		if ($this->__currentDirectory != '.') {
			$dirUrl = $dirUrl . $this->__currentDirectory;
		}
		return $dirUrl . rawurlencode($file);
	}

	private function __getDirectoryTree() {
		$dirString = $this->__currentDirectory;
		$directoryTree = array();

		$directoryTree['./'] = 'Index';

		if (substr_count($dirString, '/') >= 0) {
			$items = explode("/", $dirString);
			$items = array_filter($items);
			$path = '';
			foreach ($items as $item) {
				if ($item == '.' || $item == '..') {
					continue;
				}
				$path .= rawurlencode($item) . '/';
				$directoryTree[$path] = $item;
			}
		}

		$directoryTree = array_filter($directoryTree);

		return $directoryTree;
	}

	private function __endsWith($haystack, $needle) {
		return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
	}

	private function __generatePreview($filePath) {
		$file = $this->__getFileType($filePath);

		if ($file['mime'] == 'image/jpeg') {
			$image = imagecreatefromjpeg($file['path']);
		} elseif ($file['mime'] == 'image/png') {
			$image = imagecreatefrompng($file['path']);
		} elseif ($file['mime'] == 'image/gif') {
			$image = imagecreatefromgif($file['path']);
		} else {
			die();
		}

		$oldX = imageSX($image);
		$oldY = imageSY($image);

		$newW = 250;
		$newH = 250;

		if ($oldX > $oldY) {
			$thumbW = $newW;
			$thumbH = $oldY * ($newH / $oldX);
		}
		if ($oldX < $oldY) {
			$thumbW = $oldX * ($newW / $oldY);
			$thumbH = $newH;
		}
		if ($oldX == $oldY) {
			$thumbW = $newW;
			$thumbH = $newW;
		}

		header('Content-Type: ' . $file['mime']);

		$newImg = ImageCreateTrueColor($thumbW, $thumbH);

		imagecopyresampled($newImg, $image, 0, 0, 0, 0, $thumbW, $thumbH, $oldX, $oldY);

		if ($file['mime'] == 'image/jpeg') {
			imagejpeg($newImg);
		} elseif ($file['mime'] == 'image/png') {
			imagepng($newImg);
		} elseif ($file['mime'] == 'image/gif') {
			imagegif($newImg);
		}
		imagedestroy($newImg);
		die();
	}

	private function __formatSize($bytes) {
		$units = array('B', 'KB', 'MB', 'GB', 'TB');

		$bytes = max($bytes, 0);
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
		$pow = min($pow, count($units) - 1);

		$bytes /= pow(1024, $pow);

		return round($bytes, 2) . ' ' . $units[$pow];
	}

}

$listing = new DirectoryListing();

$successMsg = null;
$errorMsg = null;

if (isset($_FILES['upload'])) {
	$uploadStatus = $listing->upload();
	if ($uploadStatus == 1) {
		$successMsg = 'Your file was successfully uploaded!';
	} elseif ($uploadStatus == 2) {
		$errorMsg = 'Your file could not be uploaded. A file with that name already exists.';
	} elseif ($uploadStatus == 3) {
		$errorMsg = 'Your file could not be uploaded as the file type is blocked.';
	}
} elseif (isset($_GET['deleteFile']) && $listing->enableFileDeletion) {
	if ($listing->deleteFile()) {
		$successMsg = 'The file was successfully deleted!';
	} else {
		$errorMsg = 'The selected file could not be deleted. Please check your file permissions and try again.';
	}
} elseif (isset($_GET['dir']) && isset($_GET['delete']) && $listing->enableDirectoryDeletion) {
	if ($listing->deleteDirectory()) {
		$successMsg = 'The directory was successfully deleted!';
		unset($_GET['dir']);
	} else {
		$errorMsg = 'The selected directory could not be deleted. Please check your file permissions and try again.';
	}
}

$data = $listing->list();


function pr($data, $die = false) {
	echo '<pre>';
	print_r($data);
	echo '</pre>';

	if ($die) {
		die();
	}
}
?>
<html>
<head>
	<title>Listing of files in Directory<?php echo $data['currentPath'] . (!empty($listing->pageTitle) ? ' (' . $listing->pageTitle . ')' : null); ?></title>
	<meta name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0; minimum-scale=1.0; user-scalable=no; target-densityDpi=device-dpi" />
	<meta charset="UTF-8">
	<style>
		html{font-family:sans-serif;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%}body{margin:0}article,aside,details,figcaption,figure,footer,header,hgroup,main,menu,nav,section,summary{display:block}audio,canvas,progress,video{display:inline-block;vertical-align:baseline}audio:not([controls]){display:none;height:0}[hidden],template{display:none}a{background-color:transparent}a:active,a:hover{outline:0}abbr[title]{border-bottom:1px dotted}b,strong{font-weight:bold}dfn{font-style:italic}h1{font-size:2em;margin:0.67em 0}mark{background:#ff0;color:#000}small{font-size:80%}sub,sup{font-size:75%;line-height:0;position:relative;vertical-align:baseline}sup{top:-0.5em}sub{bottom:-0.25em}img{border:0}svg:not(:root){overflow:hidden}figure{margin:1em 40px}hr{-webkit-box-sizing:content-box;-moz-box-sizing:content-box;box-sizing:content-box;height:0}pre{overflow:auto}code,kbd,pre,samp{font-family:monospace, monospace;font-size:1em}button,input,optgroup,select,textarea{color:inherit;font:inherit;margin:0}button{overflow:visible}button,select{text-transform:none}button,html input[type="button"],input[type="reset"],input[type="submit"]{-webkit-appearance:button;cursor:pointer}button[disabled],html input[disabled]{cursor:default}button::-moz-focus-inner,input::-moz-focus-inner{border:0;padding:0}input{line-height:normal}input[type="checkbox"],input[type="radio"]{-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box;padding:0}input[type="number"]::-webkit-inner-spin-button,input[type="number"]::-webkit-outer-spin-button{height:auto}input[type="search"]{-webkit-appearance:textfield;-webkit-box-sizing:content-box;-moz-box-sizing:content-box;box-sizing:content-box}input[type="search"]::-webkit-search-cancel-button,input[type="search"]::-webkit-search-decoration{-webkit-appearance:none}fieldset{border:1px solid #c0c0c0;margin:0 2px;padding:0.35em 0.625em 0.75em}legend{border:0;padding:0}textarea{overflow:auto}optgroup{font-weight:bold}table{border-collapse:collapse;border-spacing:0}td,th{padding:0}*{-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}*:before,*:after{-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}html{font-size:10px;-webkit-tap-highlight-color:rgba(0,0,0,0)}body{font-family:"Helvetica Neue",Helvetica,Arial,sans-serif;font-size:14px;line-height:1.42857143;color:#333;background-color:#fff}input,button,select,textarea{font-family:inherit;font-size:inherit;line-height:inherit}a{color:#337ab7;text-decoration:none}a:hover,a:focus{color:#23527c;text-decoration:underline}a:focus{outline:thin dotted;outline:5px auto -webkit-focus-ring-color;outline-offset:-2px}figure{margin:0}img{vertical-align:middle}.img-responsive,.thumbnail>img,.thumbnail a>img{display:block;max-width:100%;height:auto}.img-rounded{border-radius:6px}.img-thumbnail{padding:4px;line-height:1.42857143;background-color:#fff;border:1px solid #ddd;border-radius:4px;-webkit-transition:all .2s ease-in-out;-o-transition:all .2s ease-in-out;transition:all .2s ease-in-out;display:inline-block;max-width:100%;height:auto}.img-circle{border-radius:50%}hr{margin-top:20px;margin-bottom:20px;border:0;border-top:1px solid #eee}.sr-only{position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0, 0, 0, 0);border:0}.sr-only-focusable:active,.sr-only-focusable:focus{position:static;width:auto;height:auto;margin:0;overflow:visible;clip:auto}[role="button"]{cursor:pointer}h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6{font-family:inherit;font-weight:500;line-height:1.1;color:inherit}h1 small,h2 small,h3 small,h4 small,h5 small,h6 small,.h1 small,.h2 small,.h3 small,.h4 small,.h5 small,.h6 small,h1 .small,h2 .small,h3 .small,h4 .small,h5 .small,h6 .small,.h1 .small,.h2 .small,.h3 .small,.h4 .small,.h5 .small,.h6 .small{font-weight:normal;line-height:1;color:#777}h1,.h1,h2,.h2,h3,.h3{margin-top:20px;margin-bottom:10px}h1 small,.h1 small,h2 small,.h2 small,h3 small,.h3 small,h1 .small,.h1 .small,h2 .small,.h2 .small,h3 .small,.h3 .small{font-size:65%}h4,.h4,h5,.h5,h6,.h6{margin-top:10px;margin-bottom:10px}h4 small,.h4 small,h5 small,.h5 small,h6 small,.h6 small,h4 .small,.h4 .small,h5 .small,.h5 .small,h6 .small,.h6 .small{font-size:75%}h1,.h1{font-size:36px}h2,.h2{font-size:30px}h3,.h3{font-size:24px}h4,.h4{font-size:18px}h5,.h5{font-size:14px}h6,.h6{font-size:12px}p{margin:0 0 10px}.lead{margin-bottom:20px;font-size:16px;font-weight:300;line-height:1.4}@media (min-width:768px){.lead{font-size:21px}}small,.small{font-size:85%}mark,.mark{background-color:#fcf8e3;padding:.2em}.text-left{text-align:left}.text-right{text-align:right}.text-center{text-align:center}.text-justify{text-align:justify}.text-nowrap{white-space:nowrap}.text-lowercase{text-transform:lowercase}.text-uppercase{text-transform:uppercase}.text-capitalize{text-transform:capitalize}.text-muted{color:#777}.text-primary{color:#337ab7}a.text-primary:hover,a.text-primary:focus{color:#286090}.text-success{color:#3c763d}a.text-success:hover,a.text-success:focus{color:#2b542c}.text-info{color:#31708f}a.text-info:hover,a.text-info:focus{color:#245269}.text-warning{color:#8a6d3b}a.text-warning:hover,a.text-warning:focus{color:#66512c}.text-danger{color:#a94442}a.text-danger:hover,a.text-danger:focus{color:#843534}.bg-primary{color:#fff;background-color:#337ab7}a.bg-primary:hover,a.bg-primary:focus{background-color:#286090}.bg-success{background-color:#dff0d8}a.bg-success:hover,a.bg-success:focus{background-color:#c1e2b3}.bg-info{background-color:#d9edf7}a.bg-info:hover,a.bg-info:focus{background-color:#afd9ee}.bg-warning{background-color:#fcf8e3}a.bg-warning:hover,a.bg-warning:focus{background-color:#f7ecb5}.bg-danger{background-color:#f2dede}a.bg-danger:hover,a.bg-danger:focus{background-color:#e4b9b9}.page-header{padding-bottom:9px;margin:40px 0 20px;border-bottom:1px solid #eee}ul,ol{margin-top:0;margin-bottom:10px}ul ul,ol ul,ul ol,ol ol{margin-bottom:0}.list-unstyled{padding-left:0;list-style:none}.list-inline{padding-left:0;list-style:none;margin-left:-5px}.list-inline>li{display:inline-block;padding-left:5px;padding-right:5px}dl{margin-top:0;margin-bottom:20px}dt,dd{line-height:1.42857143}dt{font-weight:bold}dd{margin-left:0}@media (min-width:768px){.dl-horizontal dt{float:left;width:160px;clear:left;text-align:right;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.dl-horizontal dd{margin-left:180px}}abbr[title],abbr[data-original-title]{cursor:help;border-bottom:1px dotted #777}.initialism{font-size:90%;text-transform:uppercase}blockquote{padding:10px 20px;margin:0 0 20px;font-size:17.5px;border-left:5px solid #eee}blockquote p:last-child,blockquote ul:last-child,blockquote ol:last-child{margin-bottom:0}blockquote footer,blockquote small,blockquote .small{display:block;font-size:80%;line-height:1.42857143;color:#777}blockquote footer:before,blockquote small:before,blockquote .small:before{content:'\2014 \00A0'}.blockquote-reverse,blockquote.pull-right{padding-right:15px;padding-left:0;border-right:5px solid #eee;border-left:0;text-align:right}.blockquote-reverse footer:before,blockquote.pull-right footer:before,.blockquote-reverse small:before,blockquote.pull-right small:before,.blockquote-reverse .small:before,blockquote.pull-right .small:before{content:''}.blockquote-reverse footer:after,blockquote.pull-right footer:after,.blockquote-reverse small:after,blockquote.pull-right small:after,.blockquote-reverse .small:after,blockquote.pull-right .small:after{content:'\00A0 \2014'}address{margin-bottom:20px;font-style:normal;line-height:1.42857143}.container{margin-right:auto;margin-left:auto;padding-left:15px;padding-right:15px}@media (min-width:768px){.container{width:750px}}@media (min-width:992px){.container{width:970px}}@media (min-width:1200px){.container{width:1170px}}.container-fluid{margin-right:auto;margin-left:auto;padding-left:15px;padding-right:15px}.row{margin-left:-15px;margin-right:-15px}.col-xs-1, .col-sm-1, .col-md-1, .col-lg-1, .col-xs-2, .col-sm-2, .col-md-2, .col-lg-2, .col-xs-3, .col-sm-3, .col-md-3, .col-lg-3, .col-xs-4, .col-sm-4, .col-md-4, .col-lg-4, .col-xs-5, .col-sm-5, .col-md-5, .col-lg-5, .col-xs-6, .col-sm-6, .col-md-6, .col-lg-6, .col-xs-7, .col-sm-7, .col-md-7, .col-lg-7, .col-xs-8, .col-sm-8, .col-md-8, .col-lg-8, .col-xs-9, .col-sm-9, .col-md-9, .col-lg-9, .col-xs-10, .col-sm-10, .col-md-10, .col-lg-10, .col-xs-11, .col-sm-11, .col-md-11, .col-lg-11, .col-xs-12, .col-sm-12, .col-md-12, .col-lg-12{position:relative;min-height:1px;padding-left:15px;padding-right:15px}.col-xs-1, .col-xs-2, .col-xs-3, .col-xs-4, .col-xs-5, .col-xs-6, .col-xs-7, .col-xs-8, .col-xs-9, .col-xs-10, .col-xs-11, .col-xs-12{float:left}.col-xs-12{width:100%}.col-xs-11{width:91.66666667%}.col-xs-10{width:83.33333333%}.col-xs-9{width:75%}.col-xs-8{width:66.66666667%}.col-xs-7{width:58.33333333%}.col-xs-6{width:50%}.col-xs-5{width:41.66666667%}.col-xs-4{width:33.33333333%}.col-xs-3{width:25%}.col-xs-2{width:16.66666667%}.col-xs-1{width:8.33333333%}.col-xs-pull-12{right:100%}.col-xs-pull-11{right:91.66666667%}.col-xs-pull-10{right:83.33333333%}.col-xs-pull-9{right:75%}.col-xs-pull-8{right:66.66666667%}.col-xs-pull-7{right:58.33333333%}.col-xs-pull-6{right:50%}.col-xs-pull-5{right:41.66666667%}.col-xs-pull-4{right:33.33333333%}.col-xs-pull-3{right:25%}.col-xs-pull-2{right:16.66666667%}.col-xs-pull-1{right:8.33333333%}.col-xs-pull-0{right:auto}.col-xs-push-12{left:100%}.col-xs-push-11{left:91.66666667%}.col-xs-push-10{left:83.33333333%}.col-xs-push-9{left:75%}.col-xs-push-8{left:66.66666667%}.col-xs-push-7{left:58.33333333%}.col-xs-push-6{left:50%}.col-xs-push-5{left:41.66666667%}.col-xs-push-4{left:33.33333333%}.col-xs-push-3{left:25%}.col-xs-push-2{left:16.66666667%}.col-xs-push-1{left:8.33333333%}.col-xs-push-0{left:auto}.col-xs-offset-12{margin-left:100%}.col-xs-offset-11{margin-left:91.66666667%}.col-xs-offset-10{margin-left:83.33333333%}.col-xs-offset-9{margin-left:75%}.col-xs-offset-8{margin-left:66.66666667%}.col-xs-offset-7{margin-left:58.33333333%}.col-xs-offset-6{margin-left:50%}.col-xs-offset-5{margin-left:41.66666667%}.col-xs-offset-4{margin-left:33.33333333%}.col-xs-offset-3{margin-left:25%}.col-xs-offset-2{margin-left:16.66666667%}.col-xs-offset-1{margin-left:8.33333333%}.col-xs-offset-0{margin-left:0}@media (min-width:768px){.col-sm-1, .col-sm-2, .col-sm-3, .col-sm-4, .col-sm-5, .col-sm-6, .col-sm-7, .col-sm-8, .col-sm-9, .col-sm-10, .col-sm-11, .col-sm-12{float:left}.col-sm-12{width:100%}.col-sm-11{width:91.66666667%}.col-sm-10{width:83.33333333%}.col-sm-9{width:75%}.col-sm-8{width:66.66666667%}.col-sm-7{width:58.33333333%}.col-sm-6{width:50%}.col-sm-5{width:41.66666667%}.col-sm-4{width:33.33333333%}.col-sm-3{width:25%}.col-sm-2{width:16.66666667%}.col-sm-1{width:8.33333333%}.col-sm-pull-12{right:100%}.col-sm-pull-11{right:91.66666667%}.col-sm-pull-10{right:83.33333333%}.col-sm-pull-9{right:75%}.col-sm-pull-8{right:66.66666667%}.col-sm-pull-7{right:58.33333333%}.col-sm-pull-6{right:50%}.col-sm-pull-5{right:41.66666667%}.col-sm-pull-4{right:33.33333333%}.col-sm-pull-3{right:25%}.col-sm-pull-2{right:16.66666667%}.col-sm-pull-1{right:8.33333333%}.col-sm-pull-0{right:auto}.col-sm-push-12{left:100%}.col-sm-push-11{left:91.66666667%}.col-sm-push-10{left:83.33333333%}.col-sm-push-9{left:75%}.col-sm-push-8{left:66.66666667%}.col-sm-push-7{left:58.33333333%}.col-sm-push-6{left:50%}.col-sm-push-5{left:41.66666667%}.col-sm-push-4{left:33.33333333%}.col-sm-push-3{left:25%}.col-sm-push-2{left:16.66666667%}.col-sm-push-1{left:8.33333333%}.col-sm-push-0{left:auto}.col-sm-offset-12{margin-left:100%}.col-sm-offset-11{margin-left:91.66666667%}.col-sm-offset-10{margin-left:83.33333333%}.col-sm-offset-9{margin-left:75%}.col-sm-offset-8{margin-left:66.66666667%}.col-sm-offset-7{margin-left:58.33333333%}.col-sm-offset-6{margin-left:50%}.col-sm-offset-5{margin-left:41.66666667%}.col-sm-offset-4{margin-left:33.33333333%}.col-sm-offset-3{margin-left:25%}.col-sm-offset-2{margin-left:16.66666667%}.col-sm-offset-1{margin-left:8.33333333%}.col-sm-offset-0{margin-left:0}}@media (min-width:992px){.col-md-1, .col-md-2, .col-md-3, .col-md-4, .col-md-5, .col-md-6, .col-md-7, .col-md-8, .col-md-9, .col-md-10, .col-md-11, .col-md-12{float:left}.col-md-12{width:100%}.col-md-11{width:91.66666667%}.col-md-10{width:83.33333333%}.col-md-9{width:75%}.col-md-8{width:66.66666667%}.col-md-7{width:58.33333333%}.col-md-6{width:50%}.col-md-5{width:41.66666667%}.col-md-4{width:33.33333333%}.col-md-3{width:25%}.col-md-2{width:16.66666667%}.col-md-1{width:8.33333333%}.col-md-pull-12{right:100%}.col-md-pull-11{right:91.66666667%}.col-md-pull-10{right:83.33333333%}.col-md-pull-9{right:75%}.col-md-pull-8{right:66.66666667%}.col-md-pull-7{right:58.33333333%}.col-md-pull-6{right:50%}.col-md-pull-5{right:41.66666667%}.col-md-pull-4{right:33.33333333%}.col-md-pull-3{right:25%}.col-md-pull-2{right:16.66666667%}.col-md-pull-1{right:8.33333333%}.col-md-pull-0{right:auto}.col-md-push-12{left:100%}.col-md-push-11{left:91.66666667%}.col-md-push-10{left:83.33333333%}.col-md-push-9{left:75%}.col-md-push-8{left:66.66666667%}.col-md-push-7{left:58.33333333%}.col-md-push-6{left:50%}.col-md-push-5{left:41.66666667%}.col-md-push-4{left:33.33333333%}.col-md-push-3{left:25%}.col-md-push-2{left:16.66666667%}.col-md-push-1{left:8.33333333%}.col-md-push-0{left:auto}.col-md-offset-12{margin-left:100%}.col-md-offset-11{margin-left:91.66666667%}.col-md-offset-10{margin-left:83.33333333%}.col-md-offset-9{margin-left:75%}.col-md-offset-8{margin-left:66.66666667%}.col-md-offset-7{margin-left:58.33333333%}.col-md-offset-6{margin-left:50%}.col-md-offset-5{margin-left:41.66666667%}.col-md-offset-4{margin-left:33.33333333%}.col-md-offset-3{margin-left:25%}.col-md-offset-2{margin-left:16.66666667%}.col-md-offset-1{margin-left:8.33333333%}.col-md-offset-0{margin-left:0}}@media (min-width:1200px){.col-lg-1, .col-lg-2, .col-lg-3, .col-lg-4, .col-lg-5, .col-lg-6, .col-lg-7, .col-lg-8, .col-lg-9, .col-lg-10, .col-lg-11, .col-lg-12{float:left}.col-lg-12{width:100%}.col-lg-11{width:91.66666667%}.col-lg-10{width:83.33333333%}.col-lg-9{width:75%}.col-lg-8{width:66.66666667%}.col-lg-7{width:58.33333333%}.col-lg-6{width:50%}.col-lg-5{width:41.66666667%}.col-lg-4{width:33.33333333%}.col-lg-3{width:25%}.col-lg-2{width:16.66666667%}.col-lg-1{width:8.33333333%}.col-lg-pull-12{right:100%}.col-lg-pull-11{right:91.66666667%}.col-lg-pull-10{right:83.33333333%}.col-lg-pull-9{right:75%}.col-lg-pull-8{right:66.66666667%}.col-lg-pull-7{right:58.33333333%}.col-lg-pull-6{right:50%}.col-lg-pull-5{right:41.66666667%}.col-lg-pull-4{right:33.33333333%}.col-lg-pull-3{right:25%}.col-lg-pull-2{right:16.66666667%}.col-lg-pull-1{right:8.33333333%}.col-lg-pull-0{right:auto}.col-lg-push-12{left:100%}.col-lg-push-11{left:91.66666667%}.col-lg-push-10{left:83.33333333%}.col-lg-push-9{left:75%}.col-lg-push-8{left:66.66666667%}.col-lg-push-7{left:58.33333333%}.col-lg-push-6{left:50%}.col-lg-push-5{left:41.66666667%}.col-lg-push-4{left:33.33333333%}.col-lg-push-3{left:25%}.col-lg-push-2{left:16.66666667%}.col-lg-push-1{left:8.33333333%}.col-lg-push-0{left:auto}.col-lg-offset-12{margin-left:100%}.col-lg-offset-11{margin-left:91.66666667%}.col-lg-offset-10{margin-left:83.33333333%}.col-lg-offset-9{margin-left:75%}.col-lg-offset-8{margin-left:66.66666667%}.col-lg-offset-7{margin-left:58.33333333%}.col-lg-offset-6{margin-left:50%}.col-lg-offset-5{margin-left:41.66666667%}.col-lg-offset-4{margin-left:33.33333333%}.col-lg-offset-3{margin-left:25%}.col-lg-offset-2{margin-left:16.66666667%}.col-lg-offset-1{margin-left:8.33333333%}.col-lg-offset-0{margin-left:0}}table{background-color:transparent}caption{padding-top:8px;padding-bottom:8px;color:#777;text-align:left}th{text-align:left}.table{width:100%;max-width:100%;margin-bottom:20px}.table>thead>tr>th,.table>tbody>tr>th,.table>tfoot>tr>th,.table>thead>tr>td,.table>tbody>tr>td,.table>tfoot>tr>td{padding:8px;line-height:1.42857143;vertical-align:top;border-top:1px solid #ddd}.table>thead>tr>th{vertical-align:bottom;border-bottom:2px solid #ddd}.table>caption+thead>tr:first-child>th,.table>colgroup+thead>tr:first-child>th,.table>thead:first-child>tr:first-child>th,.table>caption+thead>tr:first-child>td,.table>colgroup+thead>tr:first-child>td,.table>thead:first-child>tr:first-child>td{border-top:0}.table>tbody+tbody{border-top:2px solid #ddd}.table .table{background-color:#fff}.table-condensed>thead>tr>th,.table-condensed>tbody>tr>th,.table-condensed>tfoot>tr>th,.table-condensed>thead>tr>td,.table-condensed>tbody>tr>td,.table-condensed>tfoot>tr>td{padding:5px}.table-bordered{border:1px solid #ddd}.table-bordered>thead>tr>th,.table-bordered>tbody>tr>th,.table-bordered>tfoot>tr>th,.table-bordered>thead>tr>td,.table-bordered>tbody>tr>td,.table-bordered>tfoot>tr>td{border:1px solid #ddd}.table-bordered>thead>tr>th,.table-bordered>thead>tr>td{border-bottom-width:2px}.table-striped>tbody>tr:nth-of-type(odd){background-color:#f9f9f9}.table-hover>tbody>tr:hover{background-color:#f5f5f5}table col[class*="col-"]{position:static;float:none;display:table-column}table td[class*="col-"],table th[class*="col-"]{position:static;float:none;display:table-cell}.table>thead>tr>td.active,.table>tbody>tr>td.active,.table>tfoot>tr>td.active,.table>thead>tr>th.active,.table>tbody>tr>th.active,.table>tfoot>tr>th.active,.table>thead>tr.active>td,.table>tbody>tr.active>td,.table>tfoot>tr.active>td,.table>thead>tr.active>th,.table>tbody>tr.active>th,.table>tfoot>tr.active>th{background-color:#f5f5f5}.table-hover>tbody>tr>td.active:hover,.table-hover>tbody>tr>th.active:hover,.table-hover>tbody>tr.active:hover>td,.table-hover>tbody>tr:hover>.active,.table-hover>tbody>tr.active:hover>th{background-color:#e8e8e8}.table>thead>tr>td.success,.table>tbody>tr>td.success,.table>tfoot>tr>td.success,.table>thead>tr>th.success,.table>tbody>tr>th.success,.table>tfoot>tr>th.success,.table>thead>tr.success>td,.table>tbody>tr.success>td,.table>tfoot>tr.success>td,.table>thead>tr.success>th,.table>tbody>tr.success>th,.table>tfoot>tr.success>th{background-color:#dff0d8}.table-hover>tbody>tr>td.success:hover,.table-hover>tbody>tr>th.success:hover,.table-hover>tbody>tr.success:hover>td,.table-hover>tbody>tr:hover>.success,.table-hover>tbody>tr.success:hover>th{background-color:#d0e9c6}.table>thead>tr>td.info,.table>tbody>tr>td.info,.table>tfoot>tr>td.info,.table>thead>tr>th.info,.table>tbody>tr>th.info,.table>tfoot>tr>th.info,.table>thead>tr.info>td,.table>tbody>tr.info>td,.table>tfoot>tr.info>td,.table>thead>tr.info>th,.table>tbody>tr.info>th,.table>tfoot>tr.info>th{background-color:#d9edf7}.table-hover>tbody>tr>td.info:hover,.table-hover>tbody>tr>th.info:hover,.table-hover>tbody>tr.info:hover>td,.table-hover>tbody>tr:hover>.info,.table-hover>tbody>tr.info:hover>th{background-color:#c4e3f3}.table>thead>tr>td.warning,.table>tbody>tr>td.warning,.table>tfoot>tr>td.warning,.table>thead>tr>th.warning,.table>tbody>tr>th.warning,.table>tfoot>tr>th.warning,.table>thead>tr.warning>td,.table>tbody>tr.warning>td,.table>tfoot>tr.warning>td,.table>thead>tr.warning>th,.table>tbody>tr.warning>th,.table>tfoot>tr.warning>th{background-color:#fcf8e3}.table-hover>tbody>tr>td.warning:hover,.table-hover>tbody>tr>th.warning:hover,.table-hover>tbody>tr.warning:hover>td,.table-hover>tbody>tr:hover>.warning,.table-hover>tbody>tr.warning:hover>th{background-color:#faf2cc}.table>thead>tr>td.danger,.table>tbody>tr>td.danger,.table>tfoot>tr>td.danger,.table>thead>tr>th.danger,.table>tbody>tr>th.danger,.table>tfoot>tr>th.danger,.table>thead>tr.danger>td,.table>tbody>tr.danger>td,.table>tfoot>tr.danger>td,.table>thead>tr.danger>th,.table>tbody>tr.danger>th,.table>tfoot>tr.danger>th{background-color:#f2dede}.table-hover>tbody>tr>td.danger:hover,.table-hover>tbody>tr>th.danger:hover,.table-hover>tbody>tr.danger:hover>td,.table-hover>tbody>tr:hover>.danger,.table-hover>tbody>tr.danger:hover>th{background-color:#ebcccc}.table-responsive{overflow-x:auto;min-height:0.01%}@media screen and (max-width:767px){.table-responsive{width:100%;margin-bottom:15px;overflow-y:hidden;-ms-overflow-style:-ms-autohiding-scrollbar;border:1px solid #ddd}.table-responsive>.table{margin-bottom:0}.table-responsive>.table>thead>tr>th,.table-responsive>.table>tbody>tr>th,.table-responsive>.table>tfoot>tr>th,.table-responsive>.table>thead>tr>td,.table-responsive>.table>tbody>tr>td,.table-responsive>.table>tfoot>tr>td{white-space:nowrap}.table-responsive>.table-bordered{border:0}.table-responsive>.table-bordered>thead>tr>th:first-child,.table-responsive>.table-bordered>tbody>tr>th:first-child,.table-responsive>.table-bordered>tfoot>tr>th:first-child,.table-responsive>.table-bordered>thead>tr>td:first-child,.table-responsive>.table-bordered>tbody>tr>td:first-child,.table-responsive>.table-bordered>tfoot>tr>td:first-child{border-left:0}.table-responsive>.table-bordered>thead>tr>th:last-child,.table-responsive>.table-bordered>tbody>tr>th:last-child,.table-responsive>.table-bordered>tfoot>tr>th:last-child,.table-responsive>.table-bordered>thead>tr>td:last-child,.table-responsive>.table-bordered>tbody>tr>td:last-child,.table-responsive>.table-bordered>tfoot>tr>td:last-child{border-right:0}.table-responsive>.table-bordered>tbody>tr:last-child>th,.table-responsive>.table-bordered>tfoot>tr:last-child>th,.table-responsive>.table-bordered>tbody>tr:last-child>td,.table-responsive>.table-bordered>tfoot>tr:last-child>td{border-bottom:0}}.breadcrumb{padding:8px 15px;margin-bottom:20px;list-style:none;background-color:#f5f5f5;border-radius:4px}.breadcrumb>li{display:inline-block}.breadcrumb>li+li:before{content:"/\00a0";padding:0 5px;color:#ccc}.breadcrumb>.active{color:#777}.thumbnail{display:block;padding:4px;margin-bottom:20px;line-height:1.42857143;background-color:#fff;border:1px solid #ddd;border-radius:4px;-webkit-transition:border .2s ease-in-out;-o-transition:border .2s ease-in-out;transition:border .2s ease-in-out}.thumbnail>img,.thumbnail a>img{margin-left:auto;margin-right:auto}a.thumbnail:hover,a.thumbnail:focus,a.thumbnail.active{border-color:#337ab7}.thumbnail .caption{padding:9px;color:#333}.media{margin-top:15px}.media:first-child{margin-top:0}.media,.media-body{zoom:1;overflow:hidden}.media-body{width:10000px}.media-object{display:block}.media-object.img-thumbnail{max-width:none}.media-right,.media>.pull-right{padding-left:10px}.media-left,.media>.pull-left{padding-right:10px}.media-left,.media-right,.media-body{display:table-cell;vertical-align:top}.media-middle{vertical-align:middle}.media-bottom{vertical-align:bottom}.media-heading{margin-top:0;margin-bottom:5px}.media-list{padding-left:0;list-style:none}.tooltip{position:absolute;z-index:1070;display:block;font-family:"Helvetica Neue",Helvetica,Arial,sans-serif;font-style:normal;font-weight:normal;letter-spacing:normal;line-break:auto;line-height:1.42857143;text-align:left;text-align:start;text-decoration:none;text-shadow:none;text-transform:none;white-space:normal;word-break:normal;word-spacing:normal;word-wrap:normal;font-size:12px;opacity:0;filter:alpha(opacity=0)}.tooltip.in{opacity:.9;filter:alpha(opacity=90)}.tooltip.top{margin-top:-3px;padding:5px 0}.tooltip.right{margin-left:3px;padding:0 5px}.tooltip.bottom{margin-top:3px;padding:5px 0}.tooltip.left{margin-left:-3px;padding:0 5px}.tooltip-inner{max-width:200px;padding:3px 8px;color:#fff;text-align:center;background-color:#000;border-radius:4px}.tooltip-arrow{position:absolute;width:0;height:0;border-color:transparent;border-style:solid}.tooltip.top .tooltip-arrow{bottom:0;left:50%;margin-left:-5px;border-width:5px 5px 0;border-top-color:#000}.tooltip.top-left .tooltip-arrow{bottom:0;right:5px;margin-bottom:-5px;border-width:5px 5px 0;border-top-color:#000}.tooltip.top-right .tooltip-arrow{bottom:0;left:5px;margin-bottom:-5px;border-width:5px 5px 0;border-top-color:#000}.tooltip.right .tooltip-arrow{top:50%;left:0;margin-top:-5px;border-width:5px 5px 5px 0;border-right-color:#000}.tooltip.left .tooltip-arrow{top:50%;right:0;margin-top:-5px;border-width:5px 0 5px 5px;border-left-color:#000}.tooltip.bottom .tooltip-arrow{top:0;left:50%;margin-left:-5px;border-width:0 5px 5px;border-bottom-color:#000}.tooltip.bottom-left .tooltip-arrow{top:0;right:5px;margin-top:-5px;border-width:0 5px 5px;border-bottom-color:#000}.tooltip.bottom-right .tooltip-arrow{top:0;left:5px;margin-top:-5px;border-width:0 5px 5px;border-bottom-color:#000}.popover{position:absolute;top:0;left:0;z-index:1060;display:none;max-width:276px;padding:1px;font-family:"Helvetica Neue",Helvetica,Arial,sans-serif;font-style:normal;font-weight:normal;letter-spacing:normal;line-break:auto;line-height:1.42857143;text-align:left;text-align:start;text-decoration:none;text-shadow:none;text-transform:none;white-space:normal;word-break:normal;word-spacing:normal;word-wrap:normal;font-size:14px;background-color:#fff;-webkit-background-clip:padding-box;background-clip:padding-box;border:1px solid #ccc;border:1px solid rgba(0,0,0,0.2);border-radius:6px;-webkit-box-shadow:0 5px 10px rgba(0,0,0,0.2);box-shadow:0 5px 10px rgba(0,0,0,0.2)}.popover.top{margin-top:-10px}.popover.right{margin-left:10px}.popover.bottom{margin-top:10px}.popover.left{margin-left:-10px}.popover-title{margin:0;padding:8px 14px;font-size:14px;background-color:#f7f7f7;border-bottom:1px solid #ebebeb;border-radius:5px 5px 0 0}.popover-content{padding:9px 14px}.popover>.arrow,.popover>.arrow:after{position:absolute;display:block;width:0;height:0;border-color:transparent;border-style:solid}.popover>.arrow{border-width:11px}.popover>.arrow:after{border-width:10px;content:""}.popover.top>.arrow{left:50%;margin-left:-11px;border-bottom-width:0;border-top-color:#999;border-top-color:rgba(0,0,0,0.25);bottom:-11px}.popover.top>.arrow:after{content:" ";bottom:1px;margin-left:-10px;border-bottom-width:0;border-top-color:#fff}.popover.right>.arrow{top:50%;left:-11px;margin-top:-11px;border-left-width:0;border-right-color:#999;border-right-color:rgba(0,0,0,0.25)}.popover.right>.arrow:after{content:" ";left:1px;bottom:-10px;border-left-width:0;border-right-color:#fff}.popover.bottom>.arrow{left:50%;margin-left:-11px;border-top-width:0;border-bottom-color:#999;border-bottom-color:rgba(0,0,0,0.25);top:-11px}.popover.bottom>.arrow:after{content:" ";top:1px;margin-left:-10px;border-top-width:0;border-bottom-color:#fff}.popover.left>.arrow{top:50%;right:-11px;margin-top:-11px;border-right-width:0;border-left-color:#999;border-left-color:rgba(0,0,0,0.25)}.popover.left>.arrow:after{content:" ";right:1px;border-right-width:0;border-left-color:#fff;bottom:-10px}.clearfix:before,.clearfix:after,.dl-horizontal dd:before,.dl-horizontal dd:after,.container:before,.container:after,.container-fluid:before,.container-fluid:after,.row:before,.row:after{content:" ";display:table}.clearfix:after,.dl-horizontal dd:after,.container:after,.container-fluid:after,.row:after{clear:both}.center-block{display:block;margin-left:auto;margin-right:auto}.pull-right{float:right !important}.pull-left{float:left !important}.hide{display:none !important}.show{display:block !important}.invisible{visibility:hidden}.text-hide{font:0/0 a;color:transparent;text-shadow:none;background-color:transparent;border:0}.hidden{display:none !important}.affix{position:fixed}a.item,i{line-height:32px}a.item{padding-left:40px;width:auto!important}.preview{display:inline;position:relative;cursor:pointer}.alert,form{margin:0;},.upload-form{display:block;padding:10px!important;}.preview img{z-index:999999;position:absolute;top:50%;transform:translate(-50%);opacity:0;pointer-events:none;transition-duration:500ms;border:2px solid #fff;outline:#aaa solid 1px}.preview:hover img{opacity:1;transition-duration:500ms}.upload-form{border:1px solid #ddd;background:#fafafa;padding:10px;margin-bottom:20px!important;}.alert{margin-bottom:20px;padding:10px;border:1px solid #B8E5FF;background:#DEEDFF;color:#0A5C8C}.alert.alert-success{border-color:#BBD89B;background:#D7F7D6;color:#408C0A}.alert.alert-danger{border-color:#D89B9B;background:#F7D6D6;color:#8C0A0A}a{transition:all 200ms ease-in-out;}a:hover,a:active,a:focus{text-decoration:none;transition:all 200ms ease-in-out;color:#333;}.sort_asc{opacity:0.5;transition:all 200ms ease-in-out;width:12px!important;height:12px!important;display:inline-block;background:transparent url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMBAMAAACkW0HUAAAAJFBMVEUAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACmWAJHAAAAC3RSTlMAAQYVFiouPEC80ZaQXOoAAAAtSURBVAjXY2BAA5oFIJJp9Q4QpbV7dwOIs3v3DjAHxJ0NojYzuKUBQSADNgAAr3MQ+X9bLpEAAAAASUVORK5CYII=);}.sort_desc{opacity:0.5;transition:all 200ms ease-in-out;width:12px !important;height:12px !important;display:inline-block;background:transparent url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMBAMAAACkW0HUAAAAIVBMVEUAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABt0UjBAAAACnRSTlMAAQYVFy0xQLzRMXl+oQAAACxJREFUCFtjYMAG3NKAIJChaxUQLGbQBFEFDEyzVq1aDpTUBHEYgFwQBxsAAJ1bDw2ZcQ6sAAAAAElFTkSuQmCC);}.sort_asc:hover,.sort_desc:hover{opacity: 1;transition:all 150ms ease-in-out;}.btn {border:1px solid #1565C0;background:#1E88E5;color:#ffffff;padding:3px 5px;border-radius:3px;transition:all 150ms ease-in-out;}.btn:hover{background:#1565C0;}.btn-success{border-color:#2E7D32;background:#4CAF50;}.btn-success:hover{background:#388E3C;}.btn-block{display:block;width:100%;margin:5px 0px;}.upload-field{margin-bottom:5px;}@media(max-width: 767px){.xs-hidden{display: none;}form label{display:block!important;width:100%!important;text-align:center;}form input,form select,form textarea{display:block!important;width:100%!important;text-align:center;}form button{display:block;width:100%;margin-top:5px;}}@media(max-width: 1023px){.sm-hidden{display: none;}}.table {font-size:12px;}
	</style>
	<?php if($listing->enableTheme): ?>
		<link href="https://maxcdn.bootstrapcdn.com/bootswatch/3.3.5/yeti/bootstrap.min.css" rel="stylesheet" integrity="sha256-gJ9rCvTS5xodBImuaUYf1WfbdDKq54HCPz9wk8spvGs= sha512-weqt+X3kGDDAW9V32W7bWc6aSNCMGNQsdOpfJJz/qD/Yhp+kNeR+YyvvWojJ+afETB31L0C4eO0pcygxfTgjgw==" crossorigin="anonymous">
	<?php endif; ?>
</head>
<body>
	<div class="container-fluid">
		<?php if (! empty($listing->pageTitle)): ?>
			<div class="row">
				<div class="col-xs-12">
					<h1 class="text-center"><?php echo $listing->pageTitle; ?></h1>
				</div>
			</div>
		<?php endif; ?>

		<?php if (! empty($successMsg)): ?>
			<div class="alert alert-success"><?php echo $successMsg; ?></div>
		<?php endif; ?>

		<?php if (! empty($errorMsg)): ?>
			<div class="alert alert-danger"><?php echo $errorMsg; ?></div>
		<?php endif; ?>

		<form action="" method="post" enctype="multipart/form-data" class="text-center upload-form form-vertical">
	    	<input type='text' name='search' value='' id="search_file" placeholder="Search your file here">
	    </form>
	   	<?php 
   			$search_res = (isset($_POST['search']) && !empty($_POST['search']))?$_POST['search']:'';
   			if($search_res){?>
   				<div id="search_res">
   				</div>
   			<?php }
   		?>
	   <?php if (! empty($data['files'])): ?>
				<div class="row" id="list_file">
					<div class="col-xs-12">
						<div class="table-container">
							<table class="table table-striped table-bordered">
								<thead>
									<tr>
										<th>
											<a href="<?php echo $listing->sortUrl('name'); ?>">File <span class="<?php echo $listing->sortClass('name'); ?>"></span></a>
										</th>
										<th class="text-right xs-hidden">
											<a href="<?php echo $listing->sortUrl('size'); ?>">Size <span class="<?php echo $listing->sortClass('size'); ?>"></span></a>
										</th>
										<th class="text-right sm-hidden">
											<a href="<?php echo $listing->sortUrl('modified'); ?>">Last Modified <span class="<?php echo $listing->sortClass('modified'); ?>"></span></a>
										</th>
									</tr>
								</thead>
								<tbody id="myTable">
								<?php foreach ($data['files'] as $file): 

									?>
									<tr>
										<td>
											<a href="<?php echo $file['url']; ?>" target="<?php echo $file['target']; ?>" class="item _blank <?php echo $file['extension']; ?>">
												<?php echo $file['name']; ?>
											</a>
											<?php if (isset($file['preview']) && $file['preview']): ?>
												<span class="preview"><img src="?preview=<?php echo $file['relativePath']; ?>"><i class="preview_icon"></i></span>
											<?php endif; ?>

											<?php if ($listing->enableFileDeletion == true): ?>
												<a href="?deleteFile=<?php echo urlencode($file['relativePath']); ?>" class="pull-right btn btn-danger btn-xs" onclick="return confirm('Are you sure, you want to delete this file?')">Delete</a>
											<?php endif; ?>
										</td>
										<td class="text-right xs-hidden"><?php echo $file['size']; ?></td>
										<td class="text-right sm-hidden"><?php echo date('d-m-Y h:i:s', $file['modified']); ?></td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			<?php else: ?>
				<div class="row">
					<div class="col-xs-12">
						<p class="alert alert-info text-center">This directory does not contain any files.</p>
					</div>
				</div>
			<?php endif; ?>
			<ul class="items">
  <?php
    $options = array(
      'quantity'  => 2, // how many item to display for each page
      'around'    => 2,  // how many page btn to show around the current page btn
      'directory' => $data['files'], // dir to scan for items
    );

    $page = isset($_GET['page']) ? $_GET['page'] : 1;
    $offset = ($page - 1) * $options['quantity']; // $page base index is 1
    $filelist = scandir($options['directory']);

     //get subset of file array
    $selectedFiles = array_slice($filelist, $offset, $options['quantity']);

    foreach ($selectedFiles as $file) {
      $path = $options['directory'] . '/' . $file;
      if (is_array(getimagesize($path))) {
        echo '<li class="item"><img src="'. $path .'"></li>';
      }
    }
  ?>
</ul>
<div class="pagination">
  <a class="btn" <?= $page <= 1 ? 'disabled' : '' ?> href="?page=<?= $page - 1 ?>">&larr;</a>
  <?php
    $len = count($filelist) / $options['quantity'];
    for ($i = 1; $i < $len + 1; $i++) {
      if (($i == 1 || $i > $len) || ($i > $page - $options['around'] && $i < $page + $options['around'])) {
        echo '<a class="btn '. ($page == $i ? 'active' : '') .'" href="?page='.$i.'">'. $i .'</a>';
      } elseif ($i > $page - $options['around'] - 1 && $i < $page + $options['around'] + 1) {
        echo '<a disabled class="btn">&hellip;</a>';
      }
    }
  ?>
  <a class="btn" <?= $page >= $len ? 'disabled' : '' ?> href="?page=<?= $page + 1 ?>">&rarr;</a>
</div>
	<?php if ($data['enableUploads']): ?>
		<div class="row">
			<div class="col-xs-12">
				<form action="" method="post" enctype="multipart/form-data" class="text-center upload-form form-vertical">
					<h4>Upload A File</h4>
					<div class="row upload-field">
						<div class="col-xs-12">
							<div class="form-group">
								<div class="row">
									<div class="col-sm-2 col-md-2 col-md-offset-3 text-right">
										<label for="upload">File:</label>
									</div>
									<div class="col-sm-10 col-md-4">
										<input type="file" name="upload[]" id="upload" class="form-control">
									</div>
								</div>
							</div>
						</div>
					</div>
					<hr>
					<div class="row">
							<div class="col-xs-12 col-sm-6 col-sm-offset-3">
								<button type="submit" class="btn btn-primary btn-block" name="submit">Upload File</button>
							</div>
						</div>
				</form>
			</div>
		</div>
	<?php endif; ?>
</div>
	<style>
		._blank { width: 32px; height: 32px; display: inline-block; background: transparent url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAWBJREFUeNpi/P//PwMyKJ68eL+ytLgDA4ng/eevDO8+fVnQlxeXiE8dun1gAWRcNGnR/v9kgJ0nL/7vXLzhP1D/fEIOQMZMDFQEarKSDBqyEgmEHIEMqOoAIT4eBl1lOZIcQVUH8HBxMogJCZDkCKo6gIOdlUGAl5skR1DVASzMzAxcHGwkOYKJmJRKLGZiZGRgZWEhyREs1AyBMzfuMdx9+pLhH9Axf//9Y/j9+w/D95+/GP4zMDJwc7CDHAFSlkjQAf/JsNxGX4Ph2Zv3eNVsOnwmgTgH/CfdCRxsrAxKUmJ41XCys9E2EZKVcKkVAsSA/0Q7gFbexeIxuobA0IkCYBYe4BCgVSr4T2wI/P1HI/uJTIT/hm0iJDYK/tIsFf4fWAcQHQL//v0f2ET4h1ZRQHQa+Pt3YEPg798BTgN/aOYAYtMAraKA+BAYtmmASAfsOn2JJg54/+krhhhAgAEAOOceVk7I96wAAAAASUVORK5CYII=) top left no-repeat; }
		._page { width: 32px; height: 32px; display: inline-block; background: transparent url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA9JJREFUeNrElktoE1EUhv87j2QmD5uaVPsyrbXaGsXHQlBKoSKC6EZcVMFNi1IURBHBlYsorhQUXOnCF4puFNpNQRFSBR/gA7uxxSIt2oei1bZW7SPNeGfunckkpElGoh56embu3Nx8c/5zzw3RNA12Iyc7Yk3VoSY4tIGxn7pfQ3Rna7Z56d9nDNgd0faY9gcW7erVVl56qtHPX80FYHcBBbRttRXYsbquJReE3aRsD0nHePJmdATwBwCXag01hUTEGnzWfZVPwtrSSkiEtHRE25FLDt2yZ0AmQN8L4NUDoPMKMNzHxkyXSDK11Es8AuoCLjRHKrFrTX1emcgIoHEnsxPAIP3S/jeAxw+87AL50APiJobrAOZc3YrcAsp9IpYXyQZE87rcEFklqA4G0D89DbE4BH9lGK6aOngXl1rPS10khdotEhQrAgQ6rPvuyBKIVI7bWeSQMlcqixH6RsWbt0D1euELFONpLIYN4fKk5lQG+66SaD5VmhUCBiHSf3tW6RBouTkPhDSfBLrVU4D6+lprfLK2BkO9vdiyNmLch2XBmqvH690f0DUwigSliieAqTkNkzMapmfmUFHkaxmKto/RaUdzAiQSbNmwkkzx6+FR9H/9geHx73g9+BBlRX4cb1xJ58rG80MblqL708S8cratL8PWG4/X5ZWBBI8vB7/g+cg39Hy2Laz6jTAyA9x79xEHIwHjfoEio7Eq6Lh3ZK2Bge+/UOJTDM9ktUEV6Z21IABzfNHO7ctyLjD3NwH+hWUG4EV45s592vFokUluFkX9Wo/0Y4JIo8gioftPoE4IuwYx/szYsNhL3eM8A4/evqfdRWUuUwiXm8FINhATRgcwYAhzG0SFR8bGRQ4A4pzg7vF9BUt1fB5dMwLM8rnPet6lptpIs5CMREi+sfXWtvbMryu9suH5A3Da5rP0BPTQ41b1Agp1N02jS2FS6JJkqol0MGpHIiEcXhVyAsBi78XTBZPAXDM/AL4LXrzlEghiWqEJ7LgjGSrfkoBYoVyVUe5xIME0l6D1/GXWenUZFI9NAoVJYO0GOasEbXVBtK0I5g8wwzPw5ELhJDDXzAtgKv6fO+EUl2D7sRN8F/jYLlBU9qPUksCVuSGZEvCtuLdmoeGOAU4d2J/aA1L2f1oPMPuAVX/JfrBIkaw18wL4GWe/CGrCSwqWanNNRwDnrl5jle82K5+nXrZVv5X6tPTbzoNNJT7qXicALF1V1ctSt1tK15N4PxBTT0Ir/cRSwUNlNNfMC2CST27c1FAwCSadAEzMav93G9563v3PAH4LMACMNVxnrM+YQAAAAABJRU5ErkJggg==) top left no-repeat; }
		.css { width: 32px; height: 32px; display: inline-block; background: transparent url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAABFBJREFUeNrMVl1Mm2UUflrpD6UUSgu01A7Gjx2jwTlDGJsTF3+SQWaCWeayGJ0mXsxEjcMLr4w33i1q1CWLu8C4xE0yZYlzqHXWuSAWNwRkvw4cjo111JbSUlpGW8/7IrVrv7ZfWWM8zcn39fy87/Od9/y8kmg0injq/OCQvcZU/giyJI9vDu5Z/8fvvPLs8+nsEvfjgnje+/4n9ugK6BvHcPTNg59Fyb8rE4B4liKH1FizCoaSot2ZQMRTTgGUaNRoaajLCkROAWgKVCjXFWcFIqcAlAo5tIUFWYHICoA/MI+hS2O46XIL6mR596AgXxkDsaGhNiMIqZhMZdw/fA7b976FN979CLb+s0n6sWs3IJVIIJPlxUAYdNqMIPLEfr2tfxDFmkLUr16FB+rrkvRGfQnOXBzH2HUnIgQoHI5g4fYiAqEQClVKuLw+BmKGTF/LCCAqIMtXKmghFTp374CK3hNttm56EF+dHsCxgUG03L+WcyId6LGtExWBpG5FpJDLoaLQ5lOiCemL1So0W+/D2dGL0FM5VleU5a4PBEMLuO50QUkg0i5GOcBsLk9M4q4aUXxyTU27caD7OOSUXE1Wi2CCLjPLA9YL/pqZxXuHvkjSiwcQx6GFBczNL5VYRZnuDl0iK+h4GmoruS3zSdSvKAJmYxl2tT8KqVSKvl/PpY2A0+XB6JWr0FK17Hn6ybuIQIKj2VBKjUWL21RW6QAEKVpSiRR1lSZoKCnFABCsgkhEoM1SckkoyYR0y+T1BbiNQiZPayciB5J/EpIz/nn4PLx+f5L+ltvDu6EkzRqiAUSoiyWyrriI6xwjF3D+ykSS/sczIxgnAIxYsgqtIf4IBM5r03ordFoNfP7A0gYJNpbVZlSU6mAq18NYqhNcQzSAVAljqTKntEmnyxpAWGwG5YD+rwCWQhgIBnGSZv+lq9doAiqxbctG6glLQ+ZLe1+SnNl399rhmvFCT0m7Y+sWrs+6DFkEGO8/fAwTU7fQ3toCbVEh9nUdoZtPALaffkHf0CiXs2vY/sM93L6rpxf++SCXT9x04kjv97G1UkVVOALhMCad07hMX7jv9T38LlBjrkBzYz0NJRkfzYFgCH9OObmMMfNhzYr5uTwz2P54K/dj8uyPgGp2ju54jNiG4X9q2KjX8fcm6xrqdBE4fruAo9+eotZ7L17e9RQ6HtuMHwaGcNIxyMd32+Zm7pP1ESzSJhq1mr9P09ew/z4C9LntVOw5RxF4aWcH3n71RfxO85/lw8Gjx1FNkep8bide6GjDidMO7rvMoiOwSGHTqAvw0PpGfEjn29SwhqbcH3ymtrfmocpkRPfXdpr7Xri9ProlKVCmK4GBvvbTE9/F7BmYxZUcwbJT28MbUF9difHJG9i4zoq1NVVcZ6GL6TPbnuDyKpOB27E7AHsa6FLimfXdYb8CAP+Gy2ws55yoSyVvtNSmXEs0ANvAyH/WiP4WYABfuhLeCCyEDAAAAABJRU5ErkJggg==) top left no-repeat; }
		.doc, { width: 32px; height: 32px; display: inline-block; background: transparent url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAABKdJREFUeNrMV1tMHFUY/nZmdmZ3dne4L7CwcmmxFGgsiYkGihGjD30wUYvyYIJgUhN9IeqzNe0zMT6pD6a3xBfSxltq1TRFbamtGlMDhaJGrq3gUmAX9jazu+M/Z2G7hdmySzbWk/x7zvkv53zz32bWous60of41CtDQmHZk8hxJCIhouBJbeh03/30Nt8nmCk5WnO+H9GpMdj+mepd6ezBdiDSB4c8jmda96O+vLzX2tlz4oEAqKlyo+uJdtTlACKvANxlRWjc5cWLOYDIKwDF5UC1x50TiLwCkGwiiouUJIh6L7o6tgchZCgW9htevEXLBNWYvj4bFIe9uoHJ4+Eg1Lk/wEkyKy+B52G3SyiGkjqpC+04c2m4dzJDdQjmtZqcLZyAsG8WuqZCj8fACQJkd21KrvpuITR5Axbayw+34tOLw7g2OkEY41C1GEKhCPyBNZQqCuYW7/Sis2eFzN7M2gNSkRu8ZMfqzDgsRrysNoillSm5anjIKjKAFpq/WeKBpWXE1wJY+/5zODueBa8UUmORIe6rhHb9h/3ZhSCtWQl2F7nYjkQ0jLgaQUKNEhCJZtpHQ6RgXQdzG07PLraOLfvAyQ6I3oadlqF+D1mdRaRJT0cx1laXGE/1L8Ja6gFnkxkIdXkBekxlMm1hBmLd3i3nZA/ACHIaSa51AETRwB3Gi4UCsBaVw1pcAYvhBQMEecGQaaQj1e7dck72VWBkfNrgyeWcKDH3xzWiSBBaMACHZzcECo+6NM9yRFtZAEcgLXYHeAP0pnOyBmAxQSvKLkSYi3kEqTJEpYQu4ygEDnahTiWp+ZPeEStqTM/IIQSJLSTKCnQLqXMW8oJKeVFwV1ZQCp0XWBi0wBIkt9f0jOwBGI1nEwm8SH3AmgIhUPPZkElKKTlGYCA42QnB5jQ9I4cciJuyRcmBSNxPOWEDT+7f0BMoPwS7E7HwGqTi8oz22QNImLtLpjBIoo2KQdii4yp/CAkKDS/aM9pnDYBLZH4Cfr3xYJOOkf2gijCT5RVAvod5GT5oAKA33/8CwJn+59HWUMXWgXAUA1/9jI+HrkORJRw71IGXHmtkssFrN3Hk7CUEQlE0V5fh6KEDzM6wOXL2MgavjufWiIx3v0FGVxs4dxWVr7+Pvo++pEsPoMlThKMvtKO5qgR73v6QkbE2eIbN8cMH8ePELLPpP/Uts3FJfPK83EOgUz4k2P7K+BSu/D6Lg/vq0P14E/o++AyB1SDTGvhiGCfeeA6DwyPwlii0v8z4X/86gT1EOwiBdvetaCTk5r0RkrVgis/W6cA39Hf8LohpSaL+rRseoLUi8mjxujE6dZt54/DTj6b0jLXBG52cozyIoG23h/GbPcW48O6rZMsldbP1QPLDIvnE3R2PoK2xBt6yQgyP/YXzP41gZt6Hk2+9jAvHXkt+jlNS9r73CX3/reKd0+dwvL8bN6bn0VJTgfO/jDF+pmEx+3NaUFbB/hy21FSiwJHsbv5gGKPTf9+j295Uz2YDWPookG1oqfVg1reMGaKN4ffNf6dePNW5vQe0pAdG/py+b/wu/3bTlL/iV0kW2HkfCK4G/rNG9K8AAwD5lUIY8UX0jwAAAABJRU5ErkJggg==) top left no-repeat; }
		
		.gif { width: 32px; height: 32px; display: inline-block; background: transparent url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAABB1JREFUeNrEVu1PU2cU/11u6QttoS0lOG2kTbZElA+NCXGDD8I2ls1sCc7Ez/UPoM6/YNsfYHS67YOZgU/6bQO/zIwPlMREQzWpjIljcTA0GpBq0ZbS3red597e20ILvX3JdpKT3ufpeZ7ze87zO+c5nKIoKBXufHSmtbNzCDWKlM1C3tqawJWr5/az2+3PUsnIPTBQq39kl5YgLi9HhOgYqoEolRY0UfpDIbjb2yOIjo3/LwD6urrwWe9RuN3mQTQVQMDbgROBQ/i0txcukyAstTrJP36M3N276rdjeBiWYND4z9PWhsNerzH+dXExkq7CiYoApBKmypubEObntQzp6ED21i312z4yAq6nx7Bl7Ha0tqLL7dL5bgrEvhGQ1teRuXkTSi5XnLRa1Z/t2VnkEwnYBgdh7evTNuN5OG22kpzTYNzeB0RFALJ+KnJmP3UK29O/EYh8ud3WFrLT02pkWASmHs4jvvIPJFlGThSRIeDJdAZ+uppMJh1RomMpWnahKgC9WHDt7RDv34ci05jCy4SnVFPevoVjdBTSy5faArLjfT7cpmKEUqBWioaPqQ+2UBDb9+6FTUVAByCTI+HJE9V5S6cPcvIVrBRyYWEBudkYbB+P7FjH2+01Z07LXiRUNZ1WnbeePAnL+x8AzjYoLhf448chpTaRf/SoaGtCTZNQpjtUI9HmBD80BC4Q0E44MKj9Z7GA7++HMDdHyR8A53TWXTsqR4DunKlM5MHBQ8a49Fuhb67TD/HBA2OumpqvA4UIVJWewwDVCOlv4kkwVFcEKtcBWTK3mtitZsfyMrCxQc+oG1SDqUQSGT2eBgBIVSLACtPTVWBtzUhPMMIyxQttbCMQ9CagCj/MRUCiMaUkWJ6/fg1QAVJFd77XHn8tAceOEXv5OiMgUFHZSJLTVxoIY5XJN4xxaW0d6O6uFYCo/bLTpkg5zrzT3cKV7GcegFQ8qdPVWJPA9pCkOgE4HJo2KjUDEMtD5qGXMez3I0UZkEgmd8zFnj9HkFKQqS4rRFqm9aXhLgCRI0cw/uFHO+a8139CmLqfmc+/APfjD4i8+x6+pvKsy7fxOL6JzzUOIOzvUp1foAbk8sOEOp45cwZD3QfUaBj2herJXfmuCZVQKAIYDWk932XqCzx2GzxEqtNTU1h5s4kg9QGGfaF2KNHzGpCLFxsAIArlNYHmwt53MHP2rBbiO3cQW10t2hfshm/cKN+j5rZcEAydXFxUp76iHiBGj07o+6vFIqPnN7MtXAGzYVq6h6Hmr6BonHj2FOd++Rnjp7/EpZFPjPnJPxboShxFez3VBKEJHMjvbEAnqPGY/H0e4QMHkdrOIvFCe3A89OoNX7um2k/ENZvda+skYfkpUjQXe/PnnnMr1MI3rx8QRfxX8q8AAwC7ohz5ZBJ6IAAAAABJRU5ErkJggg==) top left no-repeat; }
		.html { width: 32px; height: 32px; display: inline-block; background: transparent url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA4tJREFUeNrkl81PE1EQwKfQFkpboEChUChI+SiCgCACUQMa8WD0YMLJBIOJEg9+oP4BxguJIRijMfEGkYPEmOAJgyhgjPINgQhVPhoQqEWFypcIdHd979ktbdnSblPx4CST93Z23rzfzs7OywoYhgF7ufmgvk2rjioGnmJeXoWFpZW6u1fPnd/Jz3k/YrDXG/cftzFeSHPXIHOn/jmD1te6A7BXP/ChpMRFgy5OVe4Owl58CqAIlkGGVsMLwqcA8iAJRIaF8oLwKUBggAgUcikvCJ8CCP39IShQzAvCz5NK9VT9BAIQCYW8IIS+zEDvRwNMzM4BjWAomobNTQusrW8AAwKQBgZgiB/I7bpbAMaLzQ9n6cD43byjz6PGlmyPMrCtW3HI3LwZosIVZL7ycw1k6AtIjInkDc6rCL+ZF+FpcztU3K6B1u4Bm31y1kRs+B724VW4nmRgaNQAHYMj0K8fs1oEoFFF2vyCZVJie9XZTzQnLRmydVrI35fmJYB1bO0agN7hUVhYXCbXCepoFDwJDqSnQliI3OYXq1JC1bULyPcTghwHw4yJ6Iu3PZCXkQrH8vejHiHmn4F3A8NkDEebnSouBN2eOJdZwm24pDCX6KRxDlre95E66RsZg73aeIiPieIPgFvr+sYm0DQD7T2DMI/eb3pyAoSQlG8X7PthfJJkbQkdz2LUEwLEIggUi10WNicA+oSJXD57BsanjTCCgk6gsWNQTzRJo4aCrDRQKkKJ39LKKnQO6WEY+bGiQU+Mnzxdm+AQ08Ma2KLVoiMWK95EP/EZ9IYpMCAYpSIEIpCyAPqJKZCg95yWqIFsVCfB1iwxbroKdwao7bgyiYQUFFbDzBeSWtYP94DjBTmgQ5vvFMNzADeNKEGtcvCTIoAUVKA0w7+Het0JfXaCchkpmv7vAXbvFfi5ygDWi7eqHeZTRhMZWW1oek3G6toGco/1W/u1bvNn17vKKjcARRF1nofKZVBZVkrmpSeK4GDmn8NmdHIaBtBBdQmdiPaCj2l2PRvDQwCaKJZ79c+IYhGLRJAYG0PmMcpwiI4IJ/OqygqYNn0lo2NHpW2xKIpHBizI2WJdUFKYR9TZjuvEYhcUH9lctcSusbgAEHIDUNuajrOdIkG3rp2fHsvDJ41kPJKbCaeLD3ECCLh+TstPFhX/jYqva3rTXnOl7Kj7z5D6x33gZffQrgH8FmAAw5hI/7HfF0AAAAAASUVORK5CYII=) top left no-repeat; }
		.jpg, .jpeg { width: 32px; height: 32px; display: inline-block; background: transparent url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAABCFJREFUeNrEV+tPU2cY/x1O762ltgtMRVdcCLLEjAW2GLy1JPtOEzPjPrVm+7INjH8BS7YP20fxD9C5GJNt7mK8JcaA31iI0pEwgaB0KCKMabm1tKfnnD3vWzjtwULPqc32pE9Oz3t5nt9zfd8jqKqKYhLO9PRbA4EQTJKcTkNJpS6i73xsu3Wb9VlKLdrR0WFWP9ITE8g+norKPd0oB6KYalBFat23D06PJ4qe7gv/C4B36+vwYfMBUyCqCmDvTh869jaYAmExqyQ7Nobs8DDUtTW4IxHU+HzanN/tQsP6O0u1O2Nj0bUyOVESgFKUqcriIiRKMF4hdjtS167x/46jR4HaWm0ty26XzYY67w6drHIgSgLYUC/Pz2P1yhVurUZWK3+sDQ5CmpqC/fBhWJua8sJEER67A/DCMAjLtrVKFtmOHEH290Gomeyrtf/yJVLXr8N14gTf89sfIxhK/AVZUZCRJCxnMlhcWcUbLheeplMsJ5K07azxEHi9UEiJqqia5WJjI9TlZdg7OwlURlsn+v24Tc0IGlABYN5gHAjA8fZ+5rVWUx5QSJH06BFXXkMKlBcvYCOXSw8eQBodhf3YMd0+0eEwXTkly1AmAJxXVrhy6/HjEA8ehEAZrno8EA8dQm7mKbKTk4W1Bth4FVAMuSdcboihEISGhjxaygk+Z7FAfP8DSFSOqKuD4HZX3DtKe4Bizlih5MHuPdq77v9bQcDpRO7+fW2sHBv2gLzugbLU0gJ1ZATy7CxQX1+RB0p3QkU2tpuyn1fHn6PAk2lgp5+OUg8PEYo6pHkAchkPsPJjCufmtPIES1jGG8TyghIXoqUKHpDpPUk9JEs1Tn0BqZSuK5Y+NGjtw4dAczOBECv0gERC5uaBpcU8CG2XwTMsu75/m/zYAkAu/2TWLi9RUxOMK91MQpE84wDkgqVuz+tdEpgMWa4QANU559cl0wByOfxXZAhAf1cX7s3M4MuhIaiffa6NJ+iwity6yZ+91JqjB5rhs9kR/2cBsbt3EV9YqPBOyAAUM2ujrDuuAwtf/QlC3znE6cLS296O3rY2hHbtwnuXL+fHKfMvhDtflWPYA3SZ2HQ+50tzY5zFlP1n4/SLtryD2O1bSNBxzShGXqlqCLiiIg/0f3QyHwK6L0Z+voqu2GkkV1N8vv/UxwjR9wGvwG+/qTAEZF2rP0DCThUsVQoeCH9/CcLXX6HxfB/ilBu/jo/jTHsbnw9f+g6RH38oeLKYzYQgsfA3gt5aDH/yKYJ0sMTIUk1ITtYJPHvzBn4hsFNfdCNBLTsUDOIiuytsoVTXp0p9nMLl4h+nPqcDrW/uRvz5MyTT+ZtxqHG/7l33aUaJ6HM4t5ynM2RAPdcXNpyESXoOLI3rpgYmxre0Jj49XaU+YMB11aJ/BRgAins+q6BEqrIAAAAASUVORK5CYII=) top left no-repeat; }
		.js { width: 32px; height: 32px; display: inline-block; background: transparent url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyJpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMy1jMDExIDY2LjE0NTY2MSwgMjAxMi8wMi8wNi0xNDo1NjoyNyAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNiAoV2luZG93cykiIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6Qjc1QkYzQkZGMTE4MTFFMUEzNjdDQjVEREQzQ0FDNEIiIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6Qjc1QkYzQzBGMTE4MTFFMUEzNjdDQjVEREQzQ0FDNEIiPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmlpZDpCNzVCRjNCREYxMTgxMUUxQTM2N0NCNURERDNDQUM0QiIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDpCNzVCRjNCRUYxMTgxMUUxQTM2N0NCNURERDNDQUM0QiIvPiA8L3JkZjpEZXNjcmlwdGlvbj4gPC9yZGY6UkRGPiA8L3g6eG1wbWV0YT4gPD94cGFja2V0IGVuZD0iciI/Pi3Sx0MAAAXoSURBVHjarFdpbFRVFP5maWfrtKW0bKW0wNiy1QItUBSFWohBKzFG1MiSIILGAioEMDExEoPIDxSBYusWksYdIv6oKBhXMFGREEoaEkpZCtTSQjttZzrLe/M85/a96XQ2Zgi3uXmv975z7rnnfN85Z3SKoiB0bNpbXzulIHe50WgAhm7FHDqdDt19LjS2tNbZTKZNLBuuVxu71q8Y8r8xirJhqx97yIYkR/PVdrRca98oBwJ2RcLalDhGhA59lDV3X78n2fPR6exBadF4zJk8YY2r37PbL8nCM3diQEKWhw8+0GaxYMGMKSgtLHjZ5fHU+CXptkYYcZcGuR5WswlZw9Jx372FfImXTjVfVmxm3bp44dDfLQNYvyk1BVaTCZnpdswrmYSywoJqt8e7J144kjago6sbHq8vigdkGPQDh2TarMi0p+H+4iJMd+StJ0x9EMuIuBjgp/Z+i0C27u09yK18CgeP/R6x3+XsRZrVLN4z0qwYNyobjnFjsGReGSpnTn6RPtzHRtwxBo7+eRJf//gbNq5citnFkyL2szLsOPZ3I7x+Gf1eL3pd/fD5JXh8fnh9PvZOtccvuenTLbc1QAl7suP6vX5Mc4zHO6+sEWuBEFCxFypKpwkmvLqzBgvLS1E1f45wuV6vR6rRSPvFOPTrX8sTMyAkBGp2go3cOzwzA929LoqvLQLVBjpoYdk0vG8zo8SRh/Kp90ToJYBeTwqEiqqYx3+dXcigg5lqMXOBHMDonOG40tYRXJOInsF3WU4OhAa6uc/vx6ff/oDT5y5gRdVCpKYYBec1AGqT1yymVKx+YjGaWi7jw28aQBQUOuIVFX08DHAM+QZnz18Sq+PHjgomHUX9TpuyetMJ9A177cz5iwRIv9ChJJ+KB54+SqWcXN6sXomJeWNw4PBRwXejwRDhAV7j1PvxoSMYOyIb2zesQjrRkXUEMZVsLQgEFEiE7HRKLNOLJqKzy4keopeebxVmAK85+9wCKzMmOygX2CATJljHIGCVxPKAhhuW02RZmYFuaTWZ1f+VYGYTB6QAFrNZUE6SA8FvNH0GfXQX6BEnBor6p+HBRbdvbL5IOcEHnV4X3Od3Ts9NFy7B5faEYEkZoiOJEAQGxBlsKuKnOvJFtntrfz2O/3sWFsKGts/vJ043YUfd54KqUybmD4RG3Q+oz4TLMWOcY6dlO25QCnJHoXrZ4zhDdCwgpHspxWr7/J4/ZgRWP/kIigsLkDsyB6FNjY5pGqMcRzWA461NbTh73RidnYW8+dlEL0m4Wq9WP5ffi7yRI+AgpshkeG+fO1KnLhC1J4juAQoB3y4QJtBDjSen5RQCGh/O3GfOWy0mIdPV0ydkwsuuLhCpKy4GBP1IuRxjMqj8siSM4LrQfrNL1Ihh6WlijfNBVDkl0RAEIFzJM3SYTSnoIyZs3lWLZY8uQsWsEmyrradS/Y+44dMPV2Dt0iqioRLMjBofWJeSaB6QFY6/HKaE2aETBYXpZiJjjpw4iS+P/Izva3eipbUNG3bswaQJ+agsn4kbt7qHyEoxWBAjBLJqwOAcXFPI1XYB0BxqQPlHyCeHGoTrv9u7HSWUMds6bw6RlVVZJbFERO6i9Ms35SdPH9EsKyNdtFxcHanNQGvbDZRTZ1T3xiacbb5Ejcg+bNt/gNJ1N6yppqDs4JSihiCqBziGEgNRVkQatVD6rfniMP441YiigrHUZnlhp0LzFbVoza3XcHj3Nhyvr6GS3YyPDjYggzwkdIRMkZYTo+FACZZULzClcqiwnLvYis8afsKiuWW0LpGrHThBBm19tw7nWq4QJlJFrzCLvOIkuoY2H4L/OiQRAhL2qwYwpdo7b2HzqmdQ9eBc4fotzz0rckHF7Jl4b+t6XG3vIBBex2vPLxNrbR03hWz4TJAFOit3NmmW1GCfz8J2qwWvv7BCNKhcePj3ASehxQ/MxpIFc4M/zzq7naIz0ockI9GYUiWlNfttDSB3df9yqsltUluvuzHYFtEvuPo7wvf+F2AAWq+5mvadKSwAAAAASUVORK5CYII=) top left no-repeat; }
		.pdf { width: 32px; height: 32px; display: inline-block; background: transparent url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAABFpJREFUeNq8V0tvG1UU/ublsT3x2E5IiFXTRLilCVQoQRWUBRIsWbAAgiKVTbtg2SrtL0BsEa3YsEKseBQqxIJusoBKhLRFSAQVnKQSEknjNA117Pj9mBfnju2xE8b1Q6bHOr6POdfnu98599wxZ1kWWuWT6OSNAM+/ih6laJqkxu/Ufe3STmK/nd1hf6Kb0ZTo6dU/7ukarFFlZms3e+NyJPpIEK3Co0/ZLOSwnHyAdLXizD393CRmpyIz1GUgQv8rgPVMCrulIu6TNkQaH8exU9M9gegbAEexPBkcRkBsRlEaGYM8MdkTiL4BiByPjdw+KobRnAuNwBOJEogJxE5NYfZEZxBivwCekH1IVkpQWhgQFBXS6LgzjtWamZW7O1eoPTdQAGFZRoYS0C9KTTq9fojqyAG7Ooizl+mLTsa5gQHg6CNw7Lspa4s/IrFyB5ZWgVkuwaSToucy0HkBqt9zlkLBjubFgQDQTYPygEOuWkXQI+NJQUT5hyWU2tA8Qem2zvMzA2HAoBOQLpch0P53iwVEFAUyJaXMCR2OHNfdKbA6aLJYtBfGQmEwl2upPeiW2XHdQI4h232Kik+AaA/KXkwGQ6joOtYJRJHKcc/HudcFaXLOLpQxop0JAzGhhrCZ3bdBjHh94HgOJtkwTngKTZhsgpKnewDt6NIo8faI/pDXSyeAxx6BKWhV5CpVclRz+k+pQOGwbKYsCnnJ0KFR/wWqkhCELgFY7hCS+byd+XnK/AwloYeKkE+ScJTCoEi1elClypgs0x1RyKNKV7TEsSib2KYx1GD/DJQ1DQVyHPb74Sc6JdoN2/XhNWw+ogRszVKhYsUqTFVziACuaJXeAaQKBeQrZTuWEhWUkM/fMVQNYYnK9FG2rgDMevsgsw+dKBVpV1XK9DGi0MRgpQ0AC3mKMXMq8rzdqkS9RxJhdtz3AACwC7ZI8a6VXBMKZb3i88HA4EVsV2zUoSGbfoEYYGpYFvC4AOj2dcfBMzwM9dlpZz67ugY9m4UveoQ02kzU2784/eHTLx34rdZn3QOo7zY4PYUXr37eLETk/KfX30Bk7i08s3DhALCb8+/a4FrtmVyfONb7OyGrXEz1esJ9dzSG6ydnUUxs4wg5ZxUvSTtrzItqAMcXzttrmCzNn7GfMW38ltYmhK4MaHXHDSZOXLwASVXhJ+qzWwkoT0VtEMxOy2bw97VvMUrUN9a98vWXdhu/8rGtPYegYpoOE61xXf30M9z95hqev7Rgu2rYMaCt41/f/wDp+CryiYQz1xOAct1xtd5+Pzd/6JhadkFidjIxE3tnDhuLi8663T/j2Ll12+U9jusOQMk06gDMA+PWHIm8fBrvbW/a44fxOJY//Ih221x3eI3tTBC7A1Cs03bvzh/44s23nXFDfvvqKv76edkZb9285fSZ/UNioOxCvSp0GYKCodfadAp7S0v/fb6xgfukrn/ZXOwdAC4vJa4AwpKMxyX/CjAALhkSt6rwJ9IAAAAASUVORK5CYII=) top left no-repeat; }
		.php { width: 32px; height: 32px; display: inline-block; background: transparent url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA8JJREFUeNrElltIFFEYx/+6s+vOrKvrbrhr2y5526SWSqKLlRSFdPNJeyuiICKCguo96KXooR7qJYguFBQ9REUQiJhBGRlR2YXspqmFZeZ1dS85M5057pxm1ZyddakPDnPON993zm++75zvTIYsy9DK4TNXGou97rUwKP3DI+gbCl06dWDHrunsJq5HFdp26PTlRjkFqWtukY+cuy4T/4t6ANqWiTTKohI/Cpy5O/UgtJJWAGeOHRXBUkMQaQWwCzzcTochiLQCWLMscORkG4JIK4CZM8FmzWIQKxaUwqMDkZnMTk22ZWZkwGLmGITHRSKhA8Gl8qUj4Qjav3aj0FsAG29l+qetbfj09TskAiOKEmK/xhCORune6B0cViAGiNlBXYAJpQI/+gdx/2kLW7yx+Tl9JoTSZIKvwI3NlctR5PUwPyUaQhzy7M36xUlFYGK1Onb+Op1MmzneZoMsJdp9/zmAi7fqyIJZGA1HqW5P7UYEi/1/jaZuCsLRGELRX+AFIfmCFCjEvq2b0DswBIFEYDrRjcCHrm7wPG9oj4jy+ByuXPvU9d/IHujpHyYhFQwBeFxOyEna6kaAI8dKILs435EzDkTCqidLygqn/WqDAGYCYMP2qgr8GArhxoMXupP63a6ZAUiSxoAz0wiYOQ4uezbts1Dn2eFx2BGKxPCxu5fpIzERFlIVU4+AJoNmpbLZBKobicVoX/ulQV8++kfC6B4aZfoQKT55HD8DAM35DridcOeMn3kHKSgbFpbgcdu38es3W2D66vIAOvvIhrVwdDyxRhhLgfwnByZTBlnIynR23oyqBb5JtlaLCQGPY5J/intAxr+SKQFESfq/AFIcoHdgEE3PXzP9vLl+lBX60NreRd+tLg9S/e3GJqwi/VmOXNpXRRmvitsY+h8YIylQmnK53LrXRCIiY3g0ghMXruHNpw68bevAw2evmJ1io9iq/Z6+Qepz9W4DbjY8ZHaGUyDFi8mWNRX02fWth3x9JyvX2lTRf4D4eMWi+QjM9dFi1Pq5C5unSenUAKKYkIo79x8hHIngvTIZue8/dHyh/b1HTyakTfV79OI13pH395qfYd2ycqY3ACAlAEhknGW2YP+2GhTNmU0nL/V7ybiWvj9w/HQcQGKnSPGpWV+J5QvnM33SAGNxBzGet6qVSxPeKQvIGjvVVh0vDZah2OdNmMsggMjKcJFvNhurkkvuBM8sF9MrNoqtMtb2Uz6GqnO+Mw+7a6snTba4rIQ2Va/YqH7a/gwA/nMhqn/y8p8B/BZgAOnDW8QALqzQAAAAAElFTkSuQmCC) top left no-repeat; }
		.png { width: 32px; height: 32px; display: inline-block; background: transparent url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAABJFJREFUeNrEVu1PU1cY/92+UAoUyltrgGBJ/ACDuErETE1Gu4Hbhy2h++BXyx8gs/+B/gUy3Zf5BV2ymS1xa40f5tBQE40IM7l0Gyhmk4EIGsECfW/v7Z5zb3tbSN8x25Oc3HPPec5zfuc5z/M7D5dMJpEt3JdjU9rmZhvKFCEchhgKXcWly6OF9Pbup8mlZDhxotz9EV5cRPz5c2di7CyKgcgWFd6hDFi6UGeod2Ls7MT/AqDP1IpPe3rKAvFOAXQYG3C8ox2flAFCU+4msSdPEH34UOrr7XZoLBZlrrG2Bp2NjUiH2e2FBWegSEzkBCBkRaq4tYW4zydnCJ0w7Lkp9auHh8EdPKjosujWa7VoNdQhO86LgSjoAeH1awSvX0cyGs0MVlVJn8i9e4jxPHQnT6Kqr082plajVqeDaY+d2wvzeUHkBCCmT0Wb6R0OhG/d2g0irRcKITw5Ca6hQfKAZ86H2aV/IIgiookEArRmIxBES00NAsEgiwk/LXMVBZAmC66+HtEHD5AkgyD3MlF3dSG5swP9yAjE7W0ZGOmpm5rwC5ERorEsb+mAJtaaUE3rItPT1pI8kAYg7gSQWH1BhrRQkRFxcxNV5PL4n38gSlegGxratU5dXV125qjyBaHUAjvSybUfDkLzwXEKQiOSdXVQH+mHsOVHbH4+o1tCKzkIReZy5onaWmiGToEzy2GlOjogz2k0UB89hvjsDCV/BzjSq1Rye0BMSk3U1yDZ2qr8o61d6Sfb2sA1NyPx+LEyVqyVzgOiUBr8zk7gdx+ElWWgvb0iD+TmgdQVFBUKTGiJF549A9bXAYoPGAzEUhSMFC+VAxCKAGCpx0796pUUD5JQakptbU3+ZyC6e4Ai8ZHHA3uuQKB/P3FIKCxvEgrK4yluyH0IQfZM73uUn+oKPRAnUiE6Br0HkkFlVYlvGDsIW28ylwsgIX/fvgWI7cBxpW9ayF7pAITMSVlg7UeYDUGoEIBeL7f9StkAErLLrEQ0Rnpemfgp8vmNDalvIxLi37yBPxaDhdLOSK9meo71rS0tu/TLz4IUgIvE/7Ysghn3zcF1/z6mPvsc3persLvdcB46hEFiSLuH+t3dmPjoY0WfgWTj/hxPeeGakAFgjR6QC4+mwV36CqOTv+Lc4fcVcDba1GY+IJMW6VmNjdLmLnolmf6R77+TvGMzmzP2SgYQj8uNDLPTnR84hjNEKkvbW/J46komqCyTUpb0Rqg29K6sYPy3WRhVahg1Wjg8HvBr6xl7lXjAQsXGIF2Dn4oNx40byklcd+5Ic2d6eyU95gkpXmjeShQ9dfq01JxUIe/LA9fm5mD/9hocP/4AfnVVOcnS5gbGZx7BQuUY03MvLMBqMuFcfz+8f/+Frq8vg2dUza6ogAc0eQHIpZGcQrkWJwRcuHsXThYXpMe/WMHozz9hwvEFLg6fkkESfbupesq3eX4AMbmuc930wB+JKP9psV+5An79JV1LhPrfKGuuzszATc+z9UAbrQvT/a9VmIYpxPzycs5p7+LTTKrt0fHTWu/2033WA4kE/iv5V4ABAGwMbZQpCDPqAAAAAElFTkSuQmCC) top left no-repeat; }
		.txt { width: 32px; height: 32px; display: inline-block; background: transparent url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAo1JREFUeNpi/P//PwMyKJ68eL+7ub4DA4ngzpMXDHefvrwAZDr25cV9wKUO3T4WbIrczPQYyAFfvv0wePn+4/6iSYvwOgIZMDFQEajLSTJoykkaAJkgRwjQ3QGCfDwMuspyJDmCqg7g5eRgEBXiJ8kRVHUAOzsrgwAPN0mOoJoDQKmbhZmZgYuDHcURGgQcwUJMViEWMDEyMrCyQBwBA3oMciDK4Maj5+DcAWR/IOgAcsGZG/dAZQHYA3/+/WP4/fsPw/efv8By3BzsBl9//JwPZAYSDgEyLLfR12B49uY9XjUz1u8WoFkUcLCxMihJiZEebQwDDKiaCIeRA0Z8FACz8EBHwUCHwN9/A+yAEZ8N//77N3gccPPBYxQ5UFUrLMDP8PjFKwZ1BVmwGIgNq4LffPiEol5WQgyleibSAYgo6Jy7HLXhCbQ0KyKAYc7abQzWBtoMakB+z4KVDHUZ8QwXbtxm2HTgGIr6koRwBnVFOfJDYEZ9McMtYCj0LVwFZsNAqLsDw6KNOxjO37jD4GRuxCAlLgLGXvaWYLVq8jIMPg5WBKMUa2349+9fFPwPagCymK6qIoMQPx8wyD8yeNmao8iBEjEIo5tDfAiglQMIByDEv//8yfD24yeG7z9+Mjx6/pJBRU4GORuBs/JfIsoTrCHwB6gRGcPSBLLY1kMnGIT4eBnsTPQZ1u4+hCL3H+zo/xjmkOCAvygY5hMY/xEw1R86cxEYx9YMzhYmDO8+fmY4cPo8XP4/NATQzSE6CtAViwkLMqSG+MLFQS3f3OhgBklRYTA/JzqI4Qew8QmT97KzZOBgZ8NpKclpgJWFhUFOUhwuzsfNDca4+GJCgljNIdoBu05foltJCBBgAP9zlklycj+AAAAAAElFTkSuQmCC) top left no-repeat; }
		.xls { width: 32px; height: 32px; display: inline-block; background: transparent url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAABENJREFUeNrEl21oW1UYx//3JbnpTdKkvbcu2WadXbHrJq5v85Mvq4iw6ZDqhO2D+IIO/WbFL4LzpfugiPhFQVRQQdgEcSCTWcSxVabD4bYy1q3LCtO2prRN29slTW6Sm3s952TJbmuSJlnYHnhIzrnPOed3nrfccJZlwS5PH2g/7m1wbkeFkkxkkNIz3xx+e/SFUnYrzxMLGd3T2Vjp+Zi6GsO1aev5pwY2YTUIu/CooXS19SCgBijE17cFIOhfj+0dvRVB1BTALyu4S22tCKKmALLkhlq/hkE8vLU8CL5wplahZJ1DdMLj8jGIDU3XIZTSEGI5N9PjBjKGlS2h/KEW+5TqBIgOHomYAZ7jIYkSQCDy0gEMDR8nEChYHWUBaJEkFmZ0pFMmzIyJTMYiQCY5XESg2Q3Z68DCbAInR05gdGIEpkVtDCTTOmLJKHxuHyLaLPWERrbrrxiAHtK0TsbVEQ1xclPAZNFr2eKH1+9kNq33NeLobz8TWw/Wb6y/sVjKaoviQ+jcfEd5HrD+PyXwHO7e7EdoeJ54AgxiciyK9m4FBvFGaHgOSlDGpi61osQVy2mX+YwlKbuuxYPxUJR5IJkwMPVPDNpcEk6XSA5Xiq6tWRlSl6sBF4HhwAs8pieWkEmb2LJNgShWXtVisTIsJSpxdVRLI6ln2B0cToHBVHj56vsAJ3Cs9GhIqCcoCPXEauvKBzCtkjpDDuM4wKdIeYjZcIL0gnTJdTXxgDars6ajkrKkWe90CXmI8N8xGCQfbtoDJqEtpDEthUWS8Y2BOhJ3nnmhaa2cTUiyU0o3MT0ZL7q+/CQ0l4/TqQz0JQPR+RSLPem4zCa3qUCz3zBZA4kupFjb9qsSZI+juipYSRtfTLM2TN1N5RrxghIU2Nwi6wFk3hJIC74eb9Kq56YScG0UqyzDFQCeBmdBG1HkoJBwFH3/M63qAOiPza2S2w5QuAoIAFW/FMCBJ35BS2MnJN7Dvm9e8yAbf9T3R94up9137mQ29NmrD3zK1tifVwwQiYYxeOFLPLn1NTzW/iLGZs7g/MRQPklXAuzteQsH/xzAm4cfRYMcRE/zjlUBCobAMG4YH7v4HbZteBz3E33/6LPsWS5EdjsqV6bPoq+zH+cnh/DtqQE2rioEtI5z6uTdqHN4Uef0QhI8bC53G7sd1Y8HX8GRc18wu30PfYgd97607HnZAPQFI6e9bXsQJ69Vv144hGd6+tlcbjO7XbC+FZ89dxqj//6Fr4bexe9XfkLrHV3LbMoPQTpr3Ky2YVfXPrzz/V5EYmG8t/sQHmnfg/FIiD1/Y+fn+TUf/PgyTl4+gv19BzExF0JbsBufDL6e36uYcIX+nLr9EvtzqtavhUxcPx65DPuYJieFswu9eQ5alrJrqOfssqQlT/yw/1Lvqh5IpbLU4cjksnn7WIueLnijsfClm29Ehm7cskb0nwADAEWS1VX7MfgcAAAAAElFTkSuQmCC) top left no-repeat; }
		.xlsx { width: 32px; height: 32px; display: inline-block; background: transparent url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAABGNJREFUeNrEV12IG1UU/mYymWQmGTebTdyku7audtdsy6ppVywo6IIFKViL+1IfLC0tgiJUpAhisfjzpj7UB6X7oviHCIpVkILKttAilK2KEprgz3a73d12f5rN5j8zmfHem2Y2WSfJBHbxwOHOufe793733HPOzHCGYaBWRt8cHFc6xUfRphTzZZQK5Y++fi1+qBlu7X6CFWgg6m93f8xNZqCm3AefeiOCViRqhcc6yvYtQxjYspWS+PB/IRDy9WB4YFdbJIT1JODzdMGndFRNQgItr2NdCUguGcHbQrVdLUkI1pHa/uZ0itMhwuNW1g41JWHLA4WchrJmVIgZlda41ToEDm5ZQD6jged4iIILcMM2CVsElheLSM4XoJZ06GWDqA6NEBKcHHwBN4I9MpILeZyPnUV8OkaIkXFdQ1EtIltIk0h3QpYklqJEDrVNILTZwzaZjC0jR05Kk0eAjv77/FB8IsNEdgbw/U9nICsCtg0HVye7KtrrlzD9p3bQHgGLGHDwHPq2+fD3H0kUSNWjJKbiKQySjcllIPHbEjwdTkQfDhHPWGe3QK7LZhBaRyFP1u25y4vJyyvM1lQD8zNZ5LMaHA4eQ7uCLCYazTfWoxDJihOBsETIcOAdHG5M55BOlrB1yEdO2H5d4xtSbaLdvTIkr2CSoFkge5wt51m5wJKAbrRW0eVgm1MSxUIZM+Rl1GqObQ8YBN1M56ezKJLa0Bl0mSRukjTNpErN59omYDTWzHKJFZ3wnV4SCzLc0qon6CtZ04yGc63uoC0P0BMuXc+j83Y3nCLP+rpoQJLsoiTo5nNXMg3nW7nAMg11vd5WS2UUSKqlb5YqgUdSzcSQRV2SQL6ISIEiJLIrKq79kyEV0gXZ62yZhkIjD9RKLqWyMiy6HcxeWSoS9ztYX4o809NLJAt049ZpSbmmnuLCHLuiZgwaeKAe6e0ULTG06HSFpKZ5XreWXQLlsoGNENtXoG8UAYsSbesKNtIFlmlIPz6ovjv6M1OR95rPO+/Yw9oqhuruyOH/YCkupPTXYW0XIprPVF/47EFmP/3AcdZS2yeF6zB9XVE8vv0ITv7wHMbjX6DPH8WJb/axvn33v4ixcy+bWMO2B0gMVPX4V0/i3t5H8PGF15ldTdHq+NRCgtlHd3+Akch+LKzMYp5o4vol+OUwfp06a2KtGFgTUHVT3xo9jcTcJRx46ASzqwFaHd/U0Y/3fzyGo5+M4NtfxvDqE59C5DwY6N6BXDGNuwNRE2voNj2gEjDVMFmcyntnXmLtyOB+M0DHDk8wvZGcwfOPvcN0745ncXriFE4+M47z8e/w+YW3cWzPKTh5D1vPKgs4q59Tj8/Ffk77woNIZ1NYJC6VXQq6/b3MDiibTHx8ZoKNbQ7cw058dTGBSM8w66dC+xfTs2yskFHx5SsxrmUaltSKrxJXYzV9KSxnUux5dunaGjwdu2jav19Zff5r7nLNd4bNOqDRF8tGlAGL+vKvAAMAJXfR/4Li3LAAAAAASUVORK5CYII=) top left no-repeat; }
		.zip { width: 32px; height: 32px; display: inline-block; background: transparent url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA9dJREFUeNqsV0tPE1EU/voUGQstpbRFRSUEeQnF9zOIT2Ki/gGNLlwYd2pITFxoYnRr4soYXRhXbmWhaJT4SFDxgVEWqI3EGCtqaWttpdN2xjt32mkLzO2t9iaHzj1zzpnvfufcey4GWZaRP+5eM521ObvOWKx2lDKSYhjR4JtR8ti7+4gU1rOb+T3zXEYt6y7C4e1hfvDG6ZzrwfMphAIP4R8+5PsVnRwavCoyQeQPow7OomKdZ9Akq7PZW9HRvs9nNlsJCKP9PwAUHwubezTRglldqHZvKwmEWZ8BiekY/f4obyapPpYqGIXlBATQAfjejd0qmg4dACSgzAbgWbYlD69EfQwmAcaKhVTFC8KsX6lsAPHQ4wLAio/BaCVpcGpaFYRMQAzogpibATlNFpVmAli28lQeAWnqA5hUFizIAyGjMf7L9/7DPaUmeokqzJcCsAEEP13Qnr2t/dQn9O0R/K9BfWVJJJhi5ICIQEoBguD0xWLBIfKym4MBKbMi/eFt6S9gzOHegMbOE7r2rmZgfOS0j7MGCGRFWDUwNQjX8ov4MX4czqUnqc7hXs/ecmSXlFADSWawutZrqKjqpr/FbAu3NxcAhQF2UINsUqsllQCsZQagpKDYqvzPjqHKsxuxqadoWnuJ8/tSKQyI7O6XlPDp1Tn63LRGLHcKknQbsUZ4chypZHZhvAB4GVDolxPsXRCL5vpCcAy2miYOAmS+bigrDKRFXYlHPpMPdmHHgQeo8WyCGJ9i2mtSEgPStP5C0glEgt/w88sI+Q3AZLYy7UsvQiWnjICVQjU61h3G2PB1uBevRrWjvrwAZClBVskO6F3SRUVlZLrcRSjyrajUwc/AtNrJyg4gzcuAkoK4Nv0e8GNo4HKBSe/eoxh7eQ913ka0r9qFm1dy3VGw1WDzrkOwO+uLpsCoXwNxTex2B3r3HKZisVbAXuOhOtq0lGM7A9a3vo/aCAuq8O7FnYIYVPhT8KcgBWbSd2rr6jDy5DY9Tjdu2090aTVg9uKR2b70IkLvE6nZaeRPwR9yqfldoJrwj2Pi41ts3NoHYb5JfZ/tGRnb0ef31RQssMHX0zcrxly3rLkZoJTljtpwKITRF4/RtqIT9fWu3Dt6b0ho857tO+Fye/LiRGc3OT4GYsQ5ok3fvBwm//uJ+DEZwEMiyujsbid/U7RnZG3lGX7/vAtkKUpsTdq8ocGB2tr5BTYWY4zqBcFCbENobVuEyookfdbvRUneFIQIW7lu2LBI2SyVM6wiGT0putQkWporqU5OsRjgBDDxNUCq3FD2cyg2PbsG/gowAPGGQK+e//OKAAAAAElFTkSuQmCC) top left no-repeat; }
		 }
	</style>
	
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
		<script>
			$(document).ready(function(){
			  $("#search_file").on("keyup", function() {
				    var value = $(this).val().toLowerCase();
				    $("#myTable tr").filter(function() {
				      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
				    });
			  });
			});

	</script>
	
</body>
</html>
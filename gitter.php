#!/usr/bin/env php
<?php 
/*
os.com.ar (a9os) - Open web LAMP framework and desktop environment
Copyright (C) 2019-2021  Santiago Pereyra (asp95)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/

class gitter_common {
	public $baseDir = __DIR__."/";
	public $gitterFolder = ".gitter";

	public $devPreventRemove = false;

	public static $arrProjectNames;

	public static $arrCommands;

	public function getProjects(){
		if (self::$arrProjectNames) return self::$arrProjectNames;

		$arrProjecsScandir = scandir($this->baseDir.$this->gitterFolder);
		self::$arrProjectNames = [];
		foreach ($arrProjecsScandir as $k => $currProjectScandir) {
			if ($currProjectScandir[0] == ".") continue;
			self::$arrProjectNames[] = $currProjectScandir;
		}

		usort(self::$arrProjectNames, function($a, $b) {
			return substr_count($b, "_") - substr_count($a, "_"); // de mas a menos
		});


		return self::$arrProjectNames;
	}

	public function getFilesAndFolders($dirToFind, $exceptFolder){
		$arrOutput = shell_exec("cd ".$dirToFind." && find . -path ./".$exceptFolder." -prune -o -type d -print");
		$arrOutput = rtrim($arrOutput, "\n");
		$arrOutput = explode("\n", $arrOutput);

		$arrOutputFiles = shell_exec("cd ".$dirToFind." && find . -path ./".$exceptFolder." -prune -o -type f -print");
		$arrOutputFiles = rtrim($arrOutputFiles, "\n");
		$arrOutputFiles = explode("\n", $arrOutputFiles);

		$arrOutput = array_merge($arrOutput,  $arrOutputFiles);

		foreach ($arrOutput as $k => $currOutput) {
			if ($currOutput != "" && strpos($currOutput, "./") === 0) $arrOutput[$k] = substr($currOutput, 2);
			if ($currOutput == "") {
				unset($arrOutput[$k]);
			} else if ($currOutput == ".") {
				unset($arrOutput[$k]);
			}
		}

		$arrOutput = array_values($arrOutput);

		return $arrOutput;
	}

	public function getDestFolder($file){ //ruta de la carpeta del archivo
		return substr($file, 0, strrpos($file, "/"));
	}

	public function filterFoldersInNoProject($arrFolders, $projectToExclude = false){
		$arrProjects = $this->getProjects();

		foreach ($arrFolders as $k => $currFolder) {
			$inAnyProject = false;
			foreach ($arrProjects as $p => $currProject) {
				if ($currProject == $projectToExclude) continue;
				if (is_dir($this->baseDir.$this->gitterFolder."/".$currProject."/".$currFolder)) {
					$inAnyProject = true;
					break;
				}
			}

			if ($inAnyProject) unset($arrFolders[$k]);
		}

		return $arrFolders;
	}

	public function getHtmlFilesByProject($projectName){
		$arrHtmlFiles = $this->getAllHtmlFilesAndFolders();

		foreach ($arrHtmlFiles as $k => $currHtmlFilePath) {
			$arrFileProjectNames = $this->decodeProjectByFile($currHtmlFilePath);

			if (!in_array($projectName, $arrFileProjectNames)) unset($arrHtmlFiles[$k]);
		}

		return $arrHtmlFiles;
	}


	public static $arrCoreFiles;

	public function decodeProjectByFile($htmlFile){
		$arrProjectOutputNames = [];

		$arrProjects = $this->getProjects();

		if (is_null(self::$arrCoreFiles) || empty(self::$arrCoreFiles)) {
			self::$arrCoreFiles = $this->getProjectFiles(["core"]);
			self::$arrCoreFiles = self::$arrCoreFiles["core"];
		}

		$originalHtmlFile = $htmlFile;
		$fileIsDir = is_dir($this->baseDir.$originalHtmlFile);

		$htmlFile = str_replace("_", "", $htmlFile);
		$htmlFile = str_replace("/", "_", $htmlFile);
		$htmlFile .= "_";

		if (in_array($originalHtmlFile, self::$arrCoreFiles)){
			$arrProjectOutputNames[] = "core";
			if (!$fileIsDir) {
				return $arrProjectOutputNames;

			}
			
		}

		foreach ($arrProjects as $k => $currProject) {
			if ($currProject == "core") continue;


			if (strpos($htmlFile, "_".$currProject."_") !== false) {
				$initPosString = substr($htmlFile, 0, strpos($htmlFile, "_".$currProject."_"));
				if (substr_count($initPosString, "_") < 2) { // html/css/a9os/app/vf/etc.txt -> _html_css_ a9os_app_vf
					$arrProjectOutputNames[] = $currProject;
					if (!$fileIsDir) {
						break;
					}
				}
			}
		}

		return $arrProjectOutputNames;
	}

	public function getAllHtmlFilesAndFolders(){
		return $this->getFilesAndFolders($this->baseDir, $this->gitterFolder);
	}

	public function getProjectFiles($arrProjects){
		$arrOutput = [];
		//$arrFilesToExclude = ["LICENSE", ".gitignore", "README.md", "license_in_files.txt"];

		foreach ($arrProjects as $k => $currProject) {
			$arrProjectFiles = $this->getFilesAndFolders($this->baseDir.$this->gitterFolder."/".$currProject, ".git");

			foreach ($arrProjectFiles as $k => $currProjectFile) {
				if ($currProject != "core" && strpos($currProjectFile, "html/") !== 0) unset($arrProjectFiles[$k]);
				//if (($gitignorePos = array_search($currFileToExclude, $arrProjectFiles)) !== false) unset($arrProjectFiles[$gitignorePos]);
			}

			$arrOutput[$currProject] = $arrProjectFiles;
		}


		return $arrOutput;
	}
}






///////////////////////////////////
//////////////////////////////////
//////////////////////////////////
final class gitter extends gitter_common{
	public function main(){
		global $argv;
		$this->detectArgvAvailable();

		if (!is_dir($this->baseDir.$this->gitterFolder)) {
			echo "Please start gitter. ./gitter.php start\n";

			if (isset($argv[1]) && $argv[1] == "start") {
				echo "Starting gitter...\n";

				mkdir($this->baseDir.$this->gitterFolder);
				mkdir($this->baseDir.$this->gitterFolder."/core");

				$arrCoreFilesToCopy = ["gitter.php", "COPYING", "_config.json", ".gitignore", "README.md", "license_in_files.txt"];
				$arrCoreFoldersToCopy = ["apache-config", "html"];

				foreach ($arrCoreFilesToCopy as $k => $currFileToCopy) {
					shell_exec("\cp ".$this->baseDir."/".$currFileToCopy." ".$this->baseDir.$this->gitterFolder."/core/");
				}
				foreach ($arrCoreFoldersToCopy as $k => $currFolderToCopy) {
					shell_exec("\mv ".$this->baseDir."/".$currFolderToCopy." ".$this->baseDir.$this->gitterFolder."/core/");
				}
				shell_exec("mv ".$this->baseDir."/.git ".$this->baseDir.$this->gitterFolder."/core/");

				$gitterGit = new gitter_git;
				$gitterGit->syncFromRepoToHtml("core");
				
				echo "Gitter started.\n";
			}

			exit();
		} else {
			$this->processCommand();
		}
	}

	public function processCommand(){
		global $argv;
		
		self::$arrCommands = [
			"list" => "gitter_list",
			"start" => "gitter_start",
			"addrepo" => "gitter_addrepo",
			"makerepo" => "gitter_makerepo",
			"help" => "gitter_help"
		];
		$commandUsed = false;
		foreach (self::$arrCommands as $command => $model) {
			if (isset($argv[1]) && strtolower($argv[1]) == $command) {
				$commandUsed = true;

				$arrModelMethod = explode("::", trim($model));
				$commandModel = new $arrModelMethod[0];
				if (isset($arrModelMethod[1])) {
					$method = $arrModelMethod[1];
					$commandModel->$method();
				} else {
					$commandModel->main();
				}
			}
		}

		if (!$commandUsed) {
			if (isset($argv[1]) && isset($argv[2])) {
				$gitterGit = new gitter_git;
				$gitterGit->gitToProject();
			}

			if (!isset($argv[1])) {
				$gitterHelp = new gitter_help;
				$gitterHelp->main();
				return;
			}
		}
	}

	public function detectArgvAvailable(){
		global $argv;


		if (!isset($argv)) {
			echo "Please set register_argc_argv = On in ".php_ini_loaded_file()."\n";
			exit();
		}

		return;
	}
}



class gitter_start extends gitter_common {
	public function main(){
		echo "Gitter already started\n";
		return;
	}
	public function getHelpData(){
		return "Starts gitter system. Creates .gitter folder and puts CORE and other future project's .git in it.";
	}
}

final class gitter_list extends gitter_common {
	public function main(){
		$arrProjects = [];
		$arrProjectNames = $this->getProjects();
		foreach ($arrProjectNames as $k => $currProjectName) {
			$arrProjects[] = [ "name" => $currProjectName, "branch" => "", "projectUrl" => "" ];
		}


		foreach ($arrProjects as $k => $arrProject) {
			$currBranchName = shell_exec("cd ".$this->baseDir.$this->gitterFolder."/".$arrProject["name"]." && git rev-parse --abbrev-ref HEAD");
			$currProjectUrl = shell_exec("cd ".$this->baseDir.$this->gitterFolder."/".$arrProject["name"]." && git config --get remote.origin.url");
			$arrProjects[$k]["branch"] = $currBranchName;
			$arrProjects[$k]["projectUrl"] = $currProjectUrl;
		}
		echo "\n";
		foreach ($arrProjects as $k => $arrProject) {
			echo "\e[1m\e[32m[".$arrProject["name"]."]\e[0m\n";
			echo "url : ".$arrProject["projectUrl"];
			echo "branch : \e[1m".$arrProject["branch"]."\e[0m\n";
		}
	}

	public function getHelpData(){
		return "Prints list of projects. Project name, project repo URL, project git branch";
	}

}

final class gitter_git extends gitter_common {
	public function gitToProject(){
		global $argv;

		$arrProjects = $this->getProjects();
		$projectRepo = $argv[1];
		if (!in_array($projectRepo, $arrProjects)) {
			echo "Error: repo not found. Do ./gitter.php list\n";
			return;
		}
		if (!isset($argv[2])) {
			echo "Error: Please specify git command. Example: ./gitter.php a9os commit -m \"comment\"\n";
			return;
		}

		$arrArgvToGitCommand = $argv;

		foreach ($arrArgvToGitCommand as $k => $arg) {
			if (strstr($arg, " ")) $arrArgvToGitCommand[$k] = '"'.$arg.'"';
		}

		array_splice($arrArgvToGitCommand, 0, 2);
		$gitCommand = "git ".implode(" ", $arrArgvToGitCommand);

		$this->syncFromHtmlToRepo($argv[1]);
		
		print(shell_exec("cd ".$this->baseDir.$this->gitterFolder."/".$projectRepo." && ".$gitCommand));

		$this->syncFromRepoToHtml($argv[1]);
	}

	public function syncFromHtmlToRepo($projectName){
		echo "Syncing files to gitter...\n";

		$arrProjectFiles = $this->getProjectFiles([$projectName]);
		$arrProjectFiles = $arrProjectFiles[$projectName];


		$arrHtmlFiles = $this->getHtmlFilesByProject($projectName);

		/*error_log("arrProjectFiles|".var_export($arrProjectFiles, true));
		error_log("arrHtmlFiles|".var_export($arrHtmlFiles, true));
		return;*/


		foreach ($arrHtmlFiles as $k => $currHtmlFilePath) { //por cada archivo del proyecto en html
			if (!is_dir($this->baseDir.$currHtmlFilePath)) { // si es un archivo
				if (file_exists($this->baseDir.$this->gitterFolder."/".$projectName."/".$currHtmlFilePath)) { // Si existe en el proyecto
					shell_exec("\cp ".$this->baseDir.$currHtmlFilePath." ".$this->baseDir.$this->gitterFolder."/".$projectName."/".$currHtmlFilePath);
					//copio
				} else { // si no existe
					$destFolder = $this->getDestFolder($currHtmlFilePath); //obtengo la ruta de la carpeta del archivo
					shell_exec("\cp ".$this->baseDir.$currHtmlFilePath." ".$this->baseDir.$this->gitterFolder."/".$projectName."/".$destFolder);
					// copio el archivo a la ruta de la carpeta del archivo, sin el nombre
				}
			} else { // si es carpeta
				if (!file_exists($this->baseDir.$this->gitterFolder."/".$projectName."/".$currHtmlFilePath)) { // si no existe la carpeta en el proyecto
					shell_exec("mkdir -p ".$this->baseDir.$this->gitterFolder."/".$projectName."/".$currHtmlFilePath); // creo
				}
			}


			unset($arrHtmlFiles[$k]); // saco el item del array

			if (($projectFileK = array_search($currHtmlFilePath, $arrProjectFiles)) !== false) { // si el archivo existe en el array de archivos en el protecto
				unset($arrProjectFiles[$projectFileK]); // lo saco
			}
		}



		foreach ($arrProjectFiles as $k => $currProjectFilePath) { // por todos los archivos del proyecto que no están en HTML
			if (!is_dir($this->baseDir.$this->gitterFolder."/".$projectName."/".$currProjectFilePath)) { // si es un archivo

				//echo "219 rm ".$this->baseDir.$this->gitterFolder."/".$projectName."/".$currProjectFilePath."\n";
				if (!$this->devPreventRemove) shell_exec("rm ".$this->baseDir.$this->gitterFolder."/".$projectName."/".$currProjectFilePath); // borro del proyecto
				unset($arrProjectFiles[$k]); // lo saco del array
			}
		}

		$arrProjectFiles = $this->filterFoldersInNoProject($arrProjectFiles, $projectName); // ?

		usort($arrProjectFiles, function($a, $b) {
			return substr_count($b, "/") - substr_count($a, "/"); // ordeno de ruta mas deep a menos
		});

		foreach ($arrProjectFiles as $k => $currProjectFolderPath) { // por cada carpeta
			if (is_dir($this->baseDir.$this->gitterFolder."/".$projectName."/".$currProjectFolderPath)) { // si es carpeta

				//echo "229 rmdir ".$this->baseDir.$this->gitterFolder."/".$projectName."/".$currProjectFolderPath."\n";
				if (!$this->devPreventRemove) shell_exec("rmdir ".$this->baseDir.$this->gitterFolder."/".$projectName."/".$currProjectFolderPath); // borro
				unset($arrProjectFiles[$k]); // saco de array
			}
		}


		$licensor = new gitter_licensor;
		$licensor->addToRepoFiles($projectName);

		return;
	}

	public function syncFromRepoToHtml($projectName){
		$licensor = new gitter_licensor;
		$licensor->removeFromRepoFiles($projectName);

		echo "Syncing gitter files to project...\n";
		$arrProjectFiles = $this->getProjectFiles([$projectName]);
		$arrProjectFiles = $arrProjectFiles[$projectName];

		$arrHtmlFiles = $this->getHtmlFilesByProject($projectName);

		/*error_log("arrProjectFiles|".var_export($arrProjectFiles, true));
		error_log("arrHtmlFiles|".var_export($arrHtmlFiles, true));
		return;*/


		foreach ($arrProjectFiles as $k => $currProjectFilePath) {
			if (!is_dir($this->baseDir.$this->gitterFolder."/".$projectName."/".$currProjectFilePath)) {
				if (file_exists($this->baseDir.$currProjectFilePath)) {
					shell_exec("\cp ".$this->baseDir.$this->gitterFolder."/".$projectName."/".$currProjectFilePath." ".$this->baseDir.$currProjectFilePath);
				} else {
					$destFolder = $this->getDestFolder($currProjectFilePath);
					shell_exec("\cp ".$this->baseDir.$this->gitterFolder."/".$projectName."/".$currProjectFilePath." ".$this->baseDir.$destFolder);
				}
			} else {
				if (!file_exists($this->baseDir.$currProjectFilePath)) {
					shell_exec("mkdir -p ".$this->baseDir.$currProjectFilePath);
				}
			}

			unset($arrProjectFiles[$k]);

			if (($htmlFileK = array_search($currProjectFilePath, $arrHtmlFiles)) !== false) {
				unset($arrHtmlFiles[$htmlFileK]);
			}
		}

		foreach ($arrHtmlFiles as $k => $currHtmlFilePath) {
			if (!is_dir($this->baseDir.$currHtmlFilePath)) {
				//echo "219 rm ".$this->baseDir.$currHtmlFilePath."\n";

				if (!$this->devPreventRemove) shell_exec("rm ".$this->baseDir.$currHtmlFilePath);
				unset($arrHtmlFiles[$k]);
			}
		}

		$arrHtmlFiles = $this->filterFoldersInNoProject($arrHtmlFiles);

		usort($arrHtmlFiles, function($a, $b) {
			return substr_count($b, "/") - substr_count($a, "/"); // de mas a menos
		});

		foreach ($arrHtmlFiles as $k => $currHtmlFilePath) {
			if (is_dir($this->baseDir.$currHtmlFilePath)) {
				//echo "229 rmdir ".$this->baseDir.$currHtmlFilePath."\n";

				if (!$this->devPreventRemove) shell_exec("rmdir ".$this->baseDir.$currHtmlFilePath);
				unset($arrHtmlFiles[$k]);
			}
		}

	}
}

final class gitter_addrepo extends gitter_common {
	public function main(){
		global $argv;

		if (!isset($argv[2])) {
			echo "Please add url repo: ./gitter.php addrepo [url]\n";
			exit();
		}

		$addrepoTempPath = $this->baseDir.$this->gitterFolder."/addrepotemp";

		shell_exec("mkdir ".$addrepoTempPath);
		shell_exec("cd ".$addrepoTempPath." && git init && git remote add origin ".$argv[2]." && git pull origin master");
		$projectName = $this->buildProjectName($addrepoTempPath);
		$newNameRepoPath = $this->baseDir.$this->gitterFolder."/".$projectName;

		$arrProjects = $this->getProjects();
		if (in_array($projectName, $arrProjects)) {
			echo "Project [".$projectName."] already exists\n";
			shell_exec("rm -rf ".$addrepoTempPath);
			exit();
		}

		shell_exec("mv ".$addrepoTempPath." ".$newNameRepoPath);

		echo "\n\nNew repo name: ".$projectName."\n\n";

		$gitterGit = new gitter_git;
		$gitterGit->syncFromRepoToHtml($projectName);

	}

	public function buildProjectName($repoPath){
		$repoBasePath = $repoPath."/html/php";
		$originRepoBasePath = $repoBasePath."/";
		do {
			$arrCdToProjOutput = $this->cdAndGetProjName($repoBasePath);
			$repoBasePath = $arrCdToProjOutput["pathToCd"];
		} while ($arrCdToProjOutput["currPathQtyFiles"] == 1);

		$projectNamePath = $arrCdToProjOutput["currPath"];
		$projectNamePath = substr($projectNamePath, strlen($originRepoBasePath));
		$projectNamePath = str_replace("_", "", $projectNamePath);
		$projectNamePath = str_replace("/", "_", $projectNamePath);

		$projectNamePath = strtolower($projectNamePath);

		return $projectNamePath;
	}

	public function cdAndGetProjName($path){
		$arrPathScandir = scandir($path);
		$qtyFiles = 0;
		$pathToCd = "";
		foreach ($arrPathScandir as $k => $currPathScandir) {
			if ($currPathScandir[0] == ".") continue;
			$qtyFiles++;
			if (is_dir($path."/".$currPathScandir)) {
				$pathToCd = $path."/".$currPathScandir;
			}
		}

		return ["pathToCd" => $pathToCd, "currPathQtyFiles" => $qtyFiles, "currPath" => $path];
	}

	public function getHelpData(){
		return "./gitter.php addrepo REPO-ORIGIN-REMOTE-URL : Adds a repo to gitter. The repo name will be automatically detected and shown in prompt.";
	}
}

final class gitter_makerepo extends gitter_common {
	public function main(){
		global $argv;

		$repoName = $argv[2];

		$newRepoGitterPath = $this->baseDir.$this->gitterFolder."/".$repoName;

		mkdir($newRepoGitterPath);

		$cdPreCommand = "cd ".$newRepoGitterPath." && ";
		print(shell_exec($cdPreCommand."git init"));
		print(shell_exec($cdPreCommand."git remote add origin ".$argv[3]));
		//print(shell_exec($cdPreCommand."git pull origin master"));

		$gitterGit = new gitter_git();
		$gitterGit->syncFromHtmlToRepo($repoName);


		print(shell_exec($cdPreCommand."git add ."));
		print(shell_exec($cdPreCommand.'git commit -m "gitter makerepo - initial commit"'));
		print(shell_exec($cdPreCommand.'git push origin master'));
		$gitterGit->syncFromRepoToHtml($repoName);


		echo "\n\nCreated: ".$repoName."\n\n";
		exit();
	}

	public function getHelpData(){
		return "./gitter.php makerepo REPO-PROJECT-NAME REPO-REMOTE-URL : Creates and push project files from new project without git to new git remote.";
	}

}

final class gitter_help extends gitter_common {
	public function main(){
		$arrCommandsMsg = [];

		foreach (self::$arrCommands as $commandName => $commandModel) {
			$arrModelMethod = explode("::", trim($commandModel));
			$modelToGetHelp = new $arrModelMethod[0];
			$modelHelp = "";
			if (method_exists($modelToGetHelp, "getHelpData")) $modelHelp = $modelToGetHelp->getHelpData();
			$arrCommandsMsg[] = [
				"name" => $commandName,
				"helpData" => $modelHelp
			];
		}
		echo "\n";
		echo "os.com.ar (a9os) - Open web LAMP framework and desktop environment\nCopyright (C) 2019-2021  Santiago Pereyra (asp95)";
		echo "\nThis program comes with ABSOLUTELY NO WARRANTY.
This is free software, and you are welcome to redistribute it
under certain conditions. Read COPYING file for details.";
		echo "\n\n\n";

		echo "Usage: ./gitter.php [REPO|COMMAND] [GET COMMAND|REPO NAME]\n";
		echo "\n";
		echo "\e[1mExample with repo\e[0m: ./gitter.php a9os commit -m \"comment\"\n";
		echo "Commands: \n";
		foreach ($arrCommandsMsg as $currCommand) {
			echo "\e[1m\e[32m".$currCommand["name"]."\e[0m: ";
			echo $currCommand["helpData"]."\n";
		}


		exit();
	}

	public function getHelpData(){
		return "Shows this help.";
	}
}


final class gitter_licensor extends gitter_common {
	public $arrFileCommentData = [
		"PHP" => [
			"startLine" => 1,
			"startStr" => "/*",
			"lineStr" => " * ",
			"endStr" => "*/",
		],
		"JS" => [
			"startLine" => 0,
			"startStr" => "/*",
			"lineStr" => " * ",
			"endStr" => "*/",
		],
		"CSS" => [
			"startLine" => 0,
			"startStr" => "/*",
			"lineStr" => " * ",
			"endStr" => "*/"
		],
		"HTML" => [
			"startLine" => 0,
			"startStr" => "<!--",
			"lineStr" => "\t",
			"endStr" => "-->",
		]
	];

	public $licenseInFilesFilename = "license_in_files.txt";

	public function addToRepoFiles($projectName){
		$arrLicenseStr = $this->getLicenseInFiles($projectName);

		if (!$arrLicenseStr) {
			echo "Project without license text for files\n";
			return false;
		}

		echo "Adding licenses to project files\n";

		$arrProjectFiles = $this->getProjectFiles([$projectName]);
		$arrProjectFiles = $arrProjectFiles[$projectName];

		$arrCompatibleExtensions = array_keys($this->arrFileCommentData);

		foreach ($arrProjectFiles as $k => $currProjectFilePath) {
			if (is_dir($this->baseDir.$this->gitterFolder."/".$projectName."/".$currProjectFilePath)) continue;
			if (substr($currProjectFilePath, 0,4) != "html") continue;

			$currFileExtension = explode(".", $currProjectFilePath);
			$currFileExtension = $currFileExtension[count($currFileExtension)-1];
			$currFileExtension = strtoupper(trim($currFileExtension));

			if (!in_array($currFileExtension, $arrCompatibleExtensions)) continue;

			$arrDataComment = $this->arrFileCommentData[$currFileExtension];

			$arrFile = file($this->baseDir.$this->gitterFolder."/".$projectName."/".$currProjectFilePath, FILE_IGNORE_NEW_LINES);

			if (strtolower(trim($arrFile[ $arrDataComment["startLine"] ])) == $arrDataComment["startStr"]."_prevent_licensor_".$arrDataComment["endStr"])
				continue;

			if (strtolower(trim($arrFile[ $arrDataComment["startLine"] ])) == $arrDataComment["startStr"]."_gtlsc_")
				continue;

			$arrCommentedLicenseStr = $this->commentLicense($arrLicenseStr, $arrDataComment);

			array_splice($arrFile, $arrDataComment["startLine"], 0, $arrCommentedLicenseStr);

			file_put_contents($this->baseDir.$this->gitterFolder."/".$projectName."/".$currProjectFilePath, implode("\n", $arrFile));
		}

	}
	public function removeFromRepoFiles($projectName){
		echo "Removing licenses to project files\n";

		$arrProjectFiles = $this->getProjectFiles([$projectName]);
		$arrProjectFiles = $arrProjectFiles[$projectName];

		$arrCompatibleExtensions = array_keys($this->arrFileCommentData);

		foreach ($arrProjectFiles as $k => $currProjectFilePath) {
			if (is_dir($this->baseDir.$this->gitterFolder."/".$projectName."/".$currProjectFilePath)) continue;
			if (substr($currProjectFilePath, 0,4) != "html") continue;

			$currFileExtension = explode(".", $currProjectFilePath);
			$currFileExtension = $currFileExtension[count($currFileExtension)-1];
			$currFileExtension = strtoupper(trim($currFileExtension));

			if (!in_array($currFileExtension, $arrCompatibleExtensions)) continue;

			$arrDataComment = $this->arrFileCommentData[$currFileExtension];

			$arrFile = file($this->baseDir.$this->gitterFolder."/".$projectName."/".$currProjectFilePath, FILE_IGNORE_NEW_LINES);

			$commandStartLine = -1;
			$commandEndLine = -1;
			foreach ($arrFile as $k => $currFileLine) {
				if (strtolower(trim($currFileLine)) == $arrDataComment["startStr"]."_prevent_licensor_".$arrDataComment["endStr"])
					continue 2;

				if (strtolower(trim($currFileLine)) == $arrDataComment["startStr"]."_gtlsc_") $commandStartLine = $k;
				if ($commandStartLine != -1 && $commandEndLine == -1 && strstr($currFileLine, $arrDataComment["endStr"])) $commandEndLine = $k;
				
			}

			if ($commandStartLine == -1 || $commandEndLine == -1) continue;

			array_splice($arrFile, $commandStartLine, $commandEndLine-$commandStartLine+1, []);

			file_put_contents($this->baseDir.$this->gitterFolder."/".$projectName."/".$currProjectFilePath, implode("\n", $arrFile));
		}
	}

	public function getLicenseInFiles($projectName){
		$projectLicenseInFilesPath = $this->baseDir.$this->gitterFolder."/".$projectName."/".$this->licenseInFilesFilename;
		if (!file_exists($projectLicenseInFilesPath)) return false;
		return file($projectLicenseInFilesPath, FILE_IGNORE_NEW_LINES);
	}

	public function commentLicense($arrLicenseStr, $arrDataComment){
		foreach ($arrLicenseStr as $k => $currLicenseLine) {
			if ($k == 0) $currLicenseLine = $arrDataComment["startStr"]."_gtlsc_\n".$arrDataComment["lineStr"].$currLicenseLine;
			elseif ($k == count($arrLicenseStr)-1) $currLicenseLine = $arrDataComment["lineStr"].$currLicenseLine.$arrDataComment["endStr"];
			else $currLicenseLine = $arrDataComment["lineStr"].$currLicenseLine;


			$arrLicenseStr[$k] = $currLicenseLine;
		}

		return $arrLicenseStr;
	}
}


$gitter = new gitter;
$gitter->main(); 

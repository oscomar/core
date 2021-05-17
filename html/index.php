<?php 
/*_gtlsc_
 * os.com.ar (a9os) - Open web LAMP framework and desktop environment
 * Copyright (C) 2019-2021  Santiago Pereyra (asp95)
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.*/
require_once "core.php";
$core = new Core();

$mainDir = $core->getMainDir();
?>
<html lang="es">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
		<link rel="stylesheet" href="<?php echo $mainDir ?>/css/core-<?php echo md5(rand(20, 40));  ?>.css">
		<link type="image/png" rel="shortcut icon" href="<?php echo $mainDir ?>/resources/app-icon.png">
		<script type="text/javascript" src="<?php echo $mainDir ?>/js/core-<?php echo md5(rand(20, 40));  ?>.js"></script>
		<script type="text/javascript">let MAIN_DIR="<?php echo $mainDir; ?>"</script>
		<link rel="manifest" href="/resources/manifest.json">
		<meta name="theme-color" content="#888888">

		<?php $core->printOgHeadData(); ?>
	</head>
	<body class="ibl-c">
		<div class="loading-splash">
			<div class="logo"></div>
			<div class="loading">Cargando...</div>
			<div class="loader"></div>
		</div>
		<div id="main-content"></div>
		<canvas id="svg-converter" width="16" height="16"></canvas>
		<!-- <div id="debugarea" style="display: block; position: fixed;color: #fff; bottom: 5px;right: 200px; width: 200px; min-height: 30px; background-color: rgba(0,0,0,0.8); padding:5px; text-align: left; font-size: 12px;word-break: break-all;"></div> -->
	</body>
</html>


<?php $core->end(); ?>

#!/bin/bash

sourcePath="$(cd -P "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
jsonConfig=$(cat ${sourcePath}/../config.json)
jsonConfig=${jsonConfig//$'\n'/}
jsonConfig=${jsonConfig//$'\t'/}
jsonConfig=${jsonConfig//\"/\\\"}
jsonConfig=${jsonConfig//\'/\\\\\'}


arrConfigFiles=$(ls $sourcePath | grep "\.conf")
for path in $arrConfigFiles
do
	currFilePath=${sourcePath}"/"${path}
	sed -i "/.*SetEnv CONFIG_JSON .*/c SetEnv CONFIG_JSON '${jsonConfig}'" $currFilePath
	echo $currFilePath
done
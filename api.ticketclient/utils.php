<!--
    Ticket Client, an application for my dad that’s managing clients, work tickets and appointments in the IT sector.
    Copyright (C) 2022 - Pimous (https://www.pimous.dev/)

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Lesser General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Lesser General Public License for more details.

    You should have received a copy of the GNU Lesser General Public License
    along with this program.  If not, see <https://www.gnu.org/licenses/>.
-->

<?php

function getFiles(string $dir): array{
    return array_values(array_filter(scandir($dir), function ($el) use ($dir){
        return is_file($dir.$el);
    }));
}

function renameCorrectlyFiles(string $dir, array $files=null){
    $dirFiles = getFiles($dir);
    $files = $files ?? $dirFiles;

    foreach($dirFiles as $name){
        if(!in_array($name, $files)) throw new Exception("Missing file(s).");
    }
    if(count($dirFiles) != count($files)) throw new Exception("Too many or too few files.");

    foreach($files as $i => $file) {
        $newName = str_pad($i, 3, "0", STR_PAD_LEFT);
        $ext = array_pop(explode(".", $file));

        foreach($dirFiles as $fileName){
            $temp = explode(".", $fileName);
            $dExt = array_pop($temp);
            $dName = implode($temp);

            if($fileName != $file && $dName == $newName){
                rename($dir.$fileName, $dir.$dName.".old.".$dExt);
                break;
            }
        }

        if(in_array($file, $dirFiles))
            rename($dir.$file, $dir.$newName.".".$ext);
        else
            rename($dir.implode(".", array_slice(explode(".", $file), 0, -1)).".old.".$ext, $dir.$newName.".".$ext);

        unset($dirFiles[array_key_first($dirFiles)]);
    }
}

// ---- Mime Type ----
function getMimeType(string $fileName): ?string {
    return [
        "png" => "image/png",
        "jpg" => "image/jpeg",
        "jpeg" => "image/jpeg"
    ][array_pop(explode(".", $fileName))];
}
function verifyMimeType(string $fileName): bool {
    return getMimeType($fileName) != null;
}
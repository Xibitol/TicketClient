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
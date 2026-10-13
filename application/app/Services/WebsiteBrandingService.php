<?php

declare(strict_types=1);
namespace Barama\Services;

use Barama\Repositories\WebsiteIdentityRepository;
use InvalidArgumentException;
use RuntimeException;

final class WebsiteBrandingService
{
    public function save(array $input, array $files): void
    {
        $repo = new WebsiteIdentityRepository();
        $values = [];
        foreach (['name','tagline','description','about','keywords','contact_email','contact_phone'] as $key) {
            if (array_key_exists($key,$input)) $values[$key]=$input[$key];
        }
        $created=[];
        try {
            foreach (['light_logo','dark_logo','favicon'] as $field) {
                if (!isset($files[$field]) || !is_array($files[$field])) continue;
                $file=$files[$field];
                $err=(int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($err === UPLOAD_ERR_NO_FILE) continue;
                if ($err !== UPLOAD_ERR_OK) throw new InvalidArgumentException('Image upload failed: '.$field);
                $tmp=(string)($file['tmp_name'] ?? '');
                $size=(int)($file['size'] ?? 0);
                if (!is_uploaded_file($tmp) || $size<1 || $size>3*1024*1024) throw new InvalidArgumentException('Upload must be an image smaller than 3 MB.');
                $info=@getimagesize($tmp);
                if (!is_array($info) || ($info[0] ?? 0)<16 || ($info[1] ?? 0)<16 || ($info[0] ?? 0)>4096 || ($info[1] ?? 0)>4096) throw new InvalidArgumentException('Invalid image dimensions.');
                $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
                $allowed=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];
                if (!isset($allowed[$mime]) || (int)($info[2] ?? 0) !== ['image/png'=>IMAGETYPE_PNG,'image/jpeg'=>IMAGETYPE_JPEG,'image/webp'=>IMAGETYPE_WEBP][$mime]) throw new InvalidArgumentException('Use PNG, JPEG or WebP images.');
                if ($field==='favicon' && $mime!=='image/png') throw new InvalidArgumentException('Favicon upload must be a PNG image.');
                $dir=BASE_PATH.'/assets/images/branding/custom';
                if (!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) throw new RuntimeException('Branding storage unavailable.');
                if (is_link($dir) || !is_writable($dir)) throw new RuntimeException('Branding storage is not writable or is unsafe.');
                $id=bin2hex(random_bytes(16));
                if ($field==='favicon') {
                    if (!extension_loaded('gd')) throw new RuntimeException('GD extension is required for favicon generation.');
                    $source=@imagecreatefrompng($tmp);
                    if (!$source) throw new InvalidArgumentException('Could not decode the favicon PNG.');
                    foreach (['favicon_16'=>16,'favicon_32'=>32,'apple_icon'=>180] as $key=>$dim) {
                        $dest=imagecreatetruecolor($dim,$dim);
                        imagealphablending($dest,false);imagesavealpha($dest,true);
                        $alpha=imagecolorallocatealpha($dest,0,0,0,127);imagefill($dest,0,0,$alpha);
                        imagecopyresampled($dest,$source,0,0,0,0,$dim,$dim,(int)$info[0],(int)$info[1]);
                        $path=$dir.'/'.bin2hex(random_bytes(16)).'.png';
                        if (!imagepng($dest,$path)) throw new RuntimeException('Could not write favicon size.');
                        imagedestroy($dest);chmod($path,0644);$created[]=$path;
                        $values[$key]='images/branding/custom/'.basename($path);
                    }
                    imagedestroy($source);
                } else {
                    $dest=$dir.'/'.$id.'.'.$allowed[$mime];
                    if (!move_uploaded_file($tmp,$dest)) throw new RuntimeException('Could not store uploaded logo.');
                    chmod($dest,0644);$created[]=$dest;
                    $values[$field]='images/branding/custom/'.basename($dest);
                }
            }
            $repo->save($values);
        } catch (\Throwable $error) {
            foreach ($created as $path) if (is_file($path)) @unlink($path);
            throw $error;
        }
    }
}

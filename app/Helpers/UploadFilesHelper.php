<?php

namespace App\Helpers;
use Symfony\Component\HttpFoundation\File\File as FileFromUrl;
use Spatie\ImageOptimizer\OptimizerChainFactory;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\ImageManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use App\Models\HubFile;
use Illuminate\Support\Str;
use Carbon\Carbon;
use ImageOptimizer;
use File;
use Storage;
use Imagick;
use DateTime;
use Image;

class UploadFilesHelper
{
    public static function __callStatic($name,$args){

    }

    public static function url_to_uploaded_file($file,$filename=null){
        $fileData = $file;
        $name= $filename==null?Str::uuid()->toString():$filename;
        $tmpFilePath = sys_get_temp_dir() . '/' . $name;
        file_put_contents($tmpFilePath, $fileData);
        $tmpFile = new FileFromUrl($tmpFilePath);
        $uploaded_file = new UploadedFile(
            $tmpFile->getPathname(),
            $tmpFile->getFilename(),
            $tmpFile->getMimeType(),
            0,
            true // Mark it as test, since the file isn't from real HTTP POST.
        ); 
        return $uploaded_file;
    }
    public static function base64_to_file($file){

        $fileData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $file));

        // save it to temporary dir first.
        $tmpFilePath = sys_get_temp_dir() . '/' . Str::uuid()->toString();
        file_put_contents($tmpFilePath, $fileData);

        // this just to help us get file info.
        $tmpFile = new FileFromUrl($tmpFilePath);

        $file = new UploadedFile(
            $tmpFile->getPathname(),
            $tmpFile->getFilename(),
            $tmpFile->getMimeType(),
            0,
            true // Mark it as test, since the file isn't from real HTTP POST.
        ); 

        return $file;
    }
    public static function formatSizeUnits($bytes){
        if ($bytes >= 1073741824)
        {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        }
        elseif ($bytes >= 1048576)
        {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        }
        elseif ($bytes >= 1024)
        {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        }
        elseif ($bytes > 1)
        {
            $bytes = $bytes . ' bytes';
        }
        elseif ($bytes == 1)
        {
            $bytes = $bytes . ' byte';
        }
        else
        {
            $bytes = '0 bytes';
        }

        return $bytes;
    } 
    public static function get_validations($type="file"){
        if($type=="file") 
            return "3gp,7z,7zip,ai,apk,avi,bin,bmp,bz2,css,csv,doc,docx,egg,flv,gif,gz,h264,htm,html,ia,icns,ico,jpeg,jpg,m4v,markdown,md,mdb,mkv,mov,mp3,mp4,mpa,mpeg,mpg,mpga,octet-stream,odp,ods,odt,ogg,otf,pak,pdf,pea,png,pps,ppt,pptx,psd,rar,rm,rss,rtf,s7z,sql,svg,tar,targz,tbz2,tex,tgz,tif,tiff,tlz,ttf,vob,wav,webm,wma,wmv,xhtml,xlr,xls,xlsx,xml,z,zip,zipx,gif,png,jpeg,qt,m4a";
        else if($type=="image")
            return "jpeg,bmp,png,gif,ico";
        else
            return $type;
    }
    public static function store_file_has_errors($file){
        $filename='';
        return [
            'success' => false, 
            'filename' => $file,
            "hasWarnings"=>"yes",
            "isSuccess"=>false,
            "warnings"=>['لم نتمكن من رفع هذا الملف'],
            "files"=>[
                [
                    "date"=>date('Y-m-d h:i:s'),
                    "extension"=>$file->extension(),
                    "file"=>$filename,
                    "format"=>"application",
                    "name"=>$filename,
                    "old_name"=>"ملف",
                    "old_title"=>"ملف",
                    "replaced"=>false,
                    "size"=>$file->getSize(),
                    "size2"=>"$file->getSize() KB",
                    "title"=>$filename,
                    "type"=>$file->getMimeType(),
                    "uploaded"=>true
                ]
            ]

        ];
    }
    public static function generate_unique_filename($file,$options){
        $t =pathinfo($file->getClientOriginalName(),PATHINFO_FILENAME); 
        $specChars = array(
            ' ' => '-',    '!' => '',    '"' => '',
            '#' => '',    '$' => '',    '%' => '',
            '&amp;' => '','&nbsp;' => '', 
            '\'' => '',   '(' => '',
            ')' => '',    '*' => '',    '+' => '',
            ',' => '',    '₹' => '',    '.' => '',
            '/-' => '',    ':' => '',    ';' => '',
            '<' => '',    '=' => '',    '>' => '',
            '?' => '',    '@' => '',    '[' => '',
            '\\' => '',   ']' => '',    '^' => '',
            '_' => '',    '`' => '',    '{' => '',
            '|' => '',    '}' => '',    '~' => '',
            '-----' => '-',    '----' => '-',    '---' => '-',
            '/' => '',    '--' => '-',   '/_' => '-',    
        ); 
        foreach ($specChars as $k => $v) {
            $t = str_replace($k, $v, $t);
        }
        $original_file_name= substr($t,0,500);
        $extension= $options['new_extension']==""?$file->extension():$options['new_extension'];
        $filename = pathinfo($original_file_name,PATHINFO_FILENAME) . '-' . substr(str_shuffle("0123456789abcdefghijklmnopqrstvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6) .'.'. $extension ;
        return $filename;
    }
    public static function store_file($options){   
        $options = array_merge([
            //'source'=>"",
            'validation'=>"file",
            'path_to_save'=>'/uploads/files/',
            'type'=>'',
            'type_id'=>"",
            'user_id'=>NULL,
            'resize'=>[400,15000],
            'small_path'=>'small/',
            'visibility'=>'PUBLIC',
            'file_system_type'=>env('FILESYSTEM_DRIVER','s3'),
            'optimize'=>true,
            'new_extension'=>"",
            'used_at'=>NULL,
        ],$options);

        $validation=Validator::make($options, [ 'source' => "required|mimes:".self::get_validations($options["validation"])."|max:250000"]);  if($validation->fails()) {
            self::store_file_has_errors($options['source']);
        }
        $user_id        = $options['user_id'];
        $file           = $options["source"];
        $path           = $options["path_to_save"]; // '/uploads/files/temp/';
        $path_small     = $options["path_to_save"] . $options["small_path"];
        $old_size       = $file->getSize();
        $new_size       = "";
        $original_name  = $file->getClientOriginalName();
        $extension = $options['new_extension']==""?$file->extension():$options['new_extension'];
        $filename = self::generate_unique_filename($file,$options);
        $file_system_type=$options["file_system_type"];



        //dd('/'.strtolower($options['visibility']) . $path . $filename, $file);
        if(null != $options["resize"] && $options['validation']=="image" && $options['optimize']==true ){

            $manager_sm  = new ImageManager(['driver' => 'imagick']);
            $image_sm    = $manager_sm->make($file);
            if(isset($options["resize"][0]))
                $image_sm = \Image::make($image_sm)->resize($options["resize"][0], null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })->encode($extension);
            $image_sm->stream();


            $manager_lg = new ImageManager(['driver' => 'imagick']);
            $image_lg   = $manager_lg->make($file);
            if(isset($options["resize"][1]))
                $image_lg = \Image::make($image_lg)->resize($options["resize"][1], null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })->encode($extension);
            $image_lg->stream();

 
            if(isset($options['optimize']) && $options['optimize']==true){
                $quality=90;
                if($image_lg->width()>=3000)
                    $quality=30;
                else if ($image_lg->width()<3000 && $image_lg->width()>=2000)
                    $quality=40;
                else if ($image_lg->width()<2000 && $image_lg->width()>=1000)
                    $quality=50;
                else
                    $quality=60;

                $imagick = new \Imagick();
                $imagick->readImageBlob($image_lg);
                $imagick->setImageCompressionQuality($quality);
                $image_lg = $imagick->getImageBlob();
            }
 

         

            try{ \Storage::disk($options["file_system_type"])->put(strtolower($options['visibility']) .$path_small .$filename, $image_sm , $filename);}catch(\Exception $e){}
            try{\Storage::disk($options["file_system_type"])->put(strtolower($options['visibility']) . $path .$filename, $image_lg); }catch(\Exception $e){}

        }else{ 
            try{\Storage::disk($options["file_system_type"])->putFileAs(strtolower($options['visibility']) . $path  , $file , $filename);}catch(\Exception $e){}
        }

        $stored_data = \App\Models\HubFile::create(
            [
                'user_id'       => $user_id,
                'path'          => $options["path_to_save"],
                'extension'     => $file->extension(),
                'size'          => $file->getSize(),
                'getMimeType'   => $file->getMimeType(),
                'type'          => $options["type"],
                'name'          => $filename,
                'original_name' => $file->getClientOriginalName(),
                'visibility'    => $options["visibility"],
                'bucket_name'   => $options["file_system_type"],
                'used_at'       => $options['used_at'],
            ]
        );  


        return [
            'success'  => true, 
            'filename' => $filename,
            'link'     => $stored_data->get_url(),
            'old_size' =>self::formatSizeUnits($old_size),
            'new_size' =>self::formatSizeUnits(
                $file->getSize()
            ),
            'saved'    =>"",
            "hasWarnings"=>false,
            "isSuccess"=>true,
            "warnings"=>[],
            "files"=>[
                [
                    "date"=>date('Y-m-d h:i:s'),
                    "extension"=>$file->extension(),
                    "file"=>$filename,
                    "format"=>"application",
                    "name"=>$filename,
                    "old_name"=>"ملف",
                    "old_title"=>"ملف",
                    "replaced"=>false,
                    "size"=>$file->getSize(),
                    "size2"=>$file->getSize(),
                    "title"=>$filename,
                    "type"=>$file->getMimeType(),
                    "uploaded"=>true
                ]
            ]
        ];
    }
    public static function remove_hub_file($name){
        $get_file = \App\Models\HubFile::where('name', $name)->first();
        if (null != $get_file && ( ($get_file['user_id'] == \Auth::user()->id ) ) ) {
            $get_file->delete(); 
            return ['success' => true, 'filename' => $name];
        }
        return ['success' => false, 'filename' => $name];
    }
    public static function use_hub_file($name, $type_id, $user_id = null, $is_main = 0){
        $get_file = \App\Models\HubFile::where('name', $name)->first();
        if (null != $get_file && $get_file['user_id'] == $user_id && null == $get_file['used_at']) {
            $get_file->update(['type_id' => $type_id, 'used_at' => now(), 'is_main' => $is_main]);
            return ['success' => true, 'filename' => $name];
        }
        return ['success' => false, 'filename' => $name];
    }


    public static function get_private_file(Request $request,HubFile $file){
        if(!self::has_access_to_get_private_file($file))abort(403);
        return redirect($file->get_temp_url());

        dd($file);

        $video=\App\Models\Video::where('url',$request->path)->firstOrFail();
        if($video->cost_type=="FREE"){
            
        }
        if($file->visibility=="PRIVATE") {
            if(\Auth::check() && (\Auth::user()->hasRole("ADMIN") || $file->user_id == \Auth::user()->id ) )
            {  
                return redirect(Storage::disk($file->bucket_name)->temporaryUrl(
                    substr($file->path, 1) .$file->name ,
                    now()->addHour()
                ));  
            }
            else if($file->type=="ticket_message"){
                  if(\Auth::check()){
                        $tm = \App\TicketMessage::where('id',$file->type_id)->firstOrFail(); 
                        if(\Auth::user()->hasRole("ADMIN") || $tm->ticket->user_id==\Auth::id()){
                              return redirect(Storage::disk($file->bucket_name)->temporaryUrl(
                                substr($file->path, 1) .$file->name ,
                                now()->addHour()
                            )); 
                        }
                  }
            }
            abort(403);
           
        }else if($file->visibility=="PUBLIC"){
            
        }else{
            abort(403);
        }

    } 
    public static function has_access_to_get_private_file(HubFile $file){
        return 1;
    }
}
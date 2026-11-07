<?php
declare(strict_types=1);

/** Load local configuration without overriding the server/process environment.
 * Values are literal: no shell execution or ${VARIABLE} interpolation.
 * Parse the complete file before modifying the process environment.
 */
function somi_load_env(?string $path=null): void
{
    $path=$path??__DIR__.'/../../.env';
    if(!is_file($path))return;
    $contents=file_get_contents($path);
    if($contents===false)throw new RuntimeException('Cannot read the local environment file.');
    $contents=preg_replace('/^\xEF\xBB\xBF/','',$contents);
    $values=[];
    foreach(preg_split('/\r\n|\n|\r/',$contents)as $number=>$line){
        $line=trim($line);
        if($line===''||str_starts_with($line,'#'))continue;
        if(!preg_match('/^(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/D',$line,$match))throw new RuntimeException('Invalid local environment syntax on line '.($number+1).'.');
        $key=$match[1];$raw=$match[2];$value='';
        if($raw!==''&&in_array($raw[0],['"',"'"],true)){
            $quote=$raw[0];$closed=false;$length=strlen($raw);
            for($i=1;$i<$length;$i++){
                $character=$raw[$i];
                if($character===$quote){$tail=trim(substr($raw,$i+1));if($tail!==''&&!str_starts_with($tail,'#'))throw new RuntimeException('Invalid local environment syntax on line '.($number+1).'.');$closed=true;break;}
                if($quote==='"'&&$character==='\\'&&$i+1<$length){$next=$raw[++$i];$character=['n'=>"\n",'r'=>"\r",'t'=>"\t",'"'=>'"','\\'=>'\\'][$next]??('\\'.$next);}
                $value.=$character;
            }
            if(!$closed)throw new RuntimeException('Invalid local environment syntax on line '.($number+1).'.');
        }else $value=str_starts_with($raw,'#')?'':rtrim(preg_replace('/\s+#.*$/','',$raw));
        if(str_contains($value,"\0"))throw new RuntimeException('Invalid local environment value on line '.($number+1).'.');
        $values[$key]=$value;
    }
    foreach($values as $key=>$value){
        // An empty template entry should not disable existing defaults.
        if($value!==''&&getenv($key)===false)putenv($key.'='.$value);
    }
}

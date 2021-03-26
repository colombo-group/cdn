<?php


namespace Colombo\Cdn;


class ArgvParser
{
    public static function parseArgs($argv){
        array_shift($argv); $o = array();
        foreach ($argv as $a){
            if (substr($a,0,2) == '--'){ $eq = strpos($a,'=');
                if ($eq !== false){ $o[substr($a,2,$eq-2)] = substr($a,$eq+1); }
                else { $k = substr($a,2); if (!isset($o[$k])){ $o[$k] = true; } } }
            else if (substr($a,0,1) == '-'){
                if (substr($a,2,1) == '='){ $o[substr($a,1,1)] = substr($a,3); }
                else { foreach (str_split(substr($a,1)) as $k){ if (!isset($o[$k])){ $o[$k] = true; } } } }
            else { $o[] = $a; } }
        return $o;
    }
}
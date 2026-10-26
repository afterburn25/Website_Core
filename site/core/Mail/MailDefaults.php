<?php
declare(strict_types=1);

namespace Core\Mail;

/** Safe first-run mail defaults derived from the installed public domain. */
final class MailDefaults
{
    /** @return array{domain:string,transport:string,host:string,port:int,secure:string,from:string} */
    public static function fromUrl(string $url): array
    {
        $host=strtolower(rtrim((string)parse_url($url,PHP_URL_HOST),'.'));
        if($host===''&&preg_match('/^[a-z0-9.-]+$/i',$url))$host=strtolower(rtrim($url,'.'));
        $domain=self::registrableDomain($host);
        return ['domain'=>$domain,'transport'=>'smtp','host'=>$domain!==''?'mail.'.$domain:'','port'=>465,'secure'=>'ssl','from'=>$domain!==''?'admin@'.$domain:''];
    }

    private static function registrableDomain(string $host): string
    {
        if($host===''||$host==='localhost'||filter_var($host,FILTER_VALIDATE_IP)||!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i',$host))return '';
        $labels=explode('.',$host);$count=count($labels);if($count<2)return '';
        $two=implode('.',array_slice($labels,-2));
        $multi=['co.uk','org.uk','net.uk','me.uk','ac.uk','gov.uk','com.au','net.au','org.au','edu.au','gov.au','co.nz','net.nz','org.nz','com.br','com.mx','co.jp','co.za','com.sg','com.my','com.ph','com.hk','com.tw','com.tr','com.cn','com.ar'];
        return in_array($two,$multi,true)&&$count>=3?implode('.',array_slice($labels,-3)):$two;
    }
}

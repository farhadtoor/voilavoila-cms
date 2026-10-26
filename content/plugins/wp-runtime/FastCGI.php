<?php
declare(strict_types=1);
namespace VV\WordPress;
defined('VV_ROOT') || exit;

/** One bounded request to a private PHP-FPM listener; no HTTP endpoint discovery. */
final class FastCGI {
    private mixed $stream;
    private float $deadline;
    private function timeout(): void {
        $left=$this->deadline-microtime(true);
        if($left<=0) throw new \VV\Failure('WordPress worker timeout.',504);
        stream_set_timeout($this->stream,(int)$left,(int)(($left-floor($left))*1000000));
    }
    private function write(string $bytes): void {
        for($at=0;$at<strlen($bytes);) {
            $this->timeout(); $n=fwrite($this->stream,substr($bytes,$at));
            if(!$n) throw new \VV\Failure('WordPress worker write failed.',502); $at+=$n;
        }
    }
    private function read(int $length): string {
        $bytes='';
        while(strlen($bytes)<$length) {
            $this->timeout(); $part=fread($this->stream,$length-strlen($bytes));
            if($part===false || $part==='') throw new \VV\Failure('WordPress worker response interrupted.',502);
            $bytes.=$part;
        } return $bytes;
    }
    private function record(int $type,string $body): void {
        $this->write(pack('CCnnCC',1,$type,1,strlen($body),0,0).$body);
    }
    private static function size(int $n): string {return $n<128?chr($n):pack('N',$n|0x80000000);}
    public function request(string $socket,array $params,string $body): array {
        if(strlen($body)>8388608) throw new \VV\Failure('WordPress request exceeds 8 MiB.',413);
        $encoded='';
        foreach($params as $key=>$value) {
            if(!is_string($key)||!is_string($value)||str_contains($key,"\0")||str_contains($value,"\0")) throw new \VV\Failure('Invalid FastCGI parameter.',500);
            $encoded.=self::size(strlen($key)).self::size(strlen($value)).$key.$value;
        }
        if(strlen($encoded)>65535) throw new \VV\Failure('WordPress parameters too large.',413);
        $this->deadline=microtime(true)+30;
        $this->stream=@stream_socket_client($socket,$error,$message,3,STREAM_CLIENT_CONNECT);
        if(!$this->stream) throw new \VV\Failure('WordPress worker unavailable; check the private pool.',503);
        try {
            $this->record(1,pack('nCxxxxx',1,0));$this->record(4,$encoded);$this->record(4,'');
            for($at=0;$at<strlen($body);$at+=65535)$this->record(5,substr($body,$at,65535));$this->record(5,'');
            $out='';$errors=0;$ended=false;
            for($i=0;$i<10000;$i++) {
                $h=unpack('Cversion/Ctype/nid/nlength/Cpadding/Creserved',$this->read(8));
                if($h['version']!==1||$h['id']!==1)throw new \VV\Failure('Invalid FastCGI record.',502);
                $data=$this->read($h['length']);if($h['padding'])$this->read($h['padding']);
                if($h['type']===6){$out.=$data;if(strlen($out)>16777216)throw new \VV\Failure('WordPress response exceeds 16 MiB.',502);}
                elseif($h['type']===7){$errors+=strlen($data);if($errors>65536)throw new \VV\Failure('WordPress worker error limit.',502);}
                elseif($h['type']===3){if(strlen($data)!==8||substr($data,0,5)!==str_repeat("\0",5))throw new \VV\Failure('WordPress worker failed.',502);$ended=true;break;}
                else throw new \VV\Failure('Unexpected FastCGI record.',502);
            }
            if(!$ended)throw new \VV\Failure('Incomplete FastCGI response.',502);
            $offset=strpos($out,"\r\n\r\n");
            if($offset===false||$offset>65536)throw new \VV\Failure('Invalid worker HTTP headers.',502);
            $headers=[];$status=200;
            foreach(explode("\r\n",substr($out,0,$offset)) as $line) {
                if(!preg_match('/^([A-Za-z0-9-]+):[ \t]*([^\x00-\x08\x0a-\x1f\x7f]*)$/D',$line,$m))throw new \VV\Failure('Unsafe worker header.',502);
                if(strtolower($m[1])==='status'){if(!preg_match('/^([1-5][0-9]{2})(?: .*)?$/D',$m[2],$s))throw new \VV\Failure('Invalid worker status.',502);$status=(int)$s[1];}
                else $headers[]=[strtolower($m[1]),$m[2]];
            }
            return ['status'=>$status,'headers'=>$headers,'body'=>substr($out,$offset+4)];
        } finally {fclose($this->stream);}
    }
}

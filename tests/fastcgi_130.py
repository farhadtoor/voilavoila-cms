#!/usr/bin/env python3
"""Hostile private FastCGI peer checks, with disposable loopback listeners."""
import argparse,json,os,socket,struct,subprocess,tempfile,threading
from pathlib import Path
SOURCE=Path(__file__).resolve().parents[1]
def record(kind,body=b'',version=1,ident=1):return struct.pack('!BBHHBB',version,kind,ident,len(body),0,0)+body
def main():
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('--php',default='php');p.add_argument('--report',type=Path);a=p.parse_args();checks=[]
 with tempfile.TemporaryDirectory(prefix='vv-fcgi-') as temp:
  script=Path(temp)/'client.php';script.write_text('<?php define("VV_ROOT",'+json.dumps(str(SOURCE))+');require VV_ROOT."/core/Store.php";require VV_ROOT."/content/plugins/wp-runtime/FastCGI.php";try{$r=(new \\VV\\WordPress\\FastCGI)->request($argv[1],["SCRIPT_FILENAME"=>"/private/Gateway.php"],"");echo json_encode($r,JSON_THROW_ON_ERROR);}catch(\\VV\\Failure $e){echo json_encode(["error"=>$e->status]);}')
  end=record(3,b'\0'*8)
  cases=[('fragmented genuine response with preserved 201',record(6,b'Status: 201 Created\r\nContent-Type: application/json\r\nSet-Cookie: a=1\r\nSet-Cookie: b=2\r\n\r\n{}')+end,201),('unexpected protocol version',record(6,b'',version=2),502),('wrong request identifier',record(6,b'',ident=2),502),('unknown record type',record(99),502),('invalid HTTP status',record(6,b'Status: 999 Bad\r\n\r\nx')+end,502),('unsafe HTTP header control',record(6,b'X-Test: a\x01b\r\n\r\nx')+end,502),('oversized HTTP headers',b''.join(record(6,b'A'*60000) for _ in range(2))+end,502),('application error at END_REQUEST',record(6,b'Content-Type: text/plain\r\n\r\nx')+record(3,b'\0\0\0\1'+b'\0'*4),502),('missing END_REQUEST',record(6,b'Content-Type: text/plain\r\n\r\nx'),502),('stderr bound',record(7,b'x'*60000)+record(7,b'x'*60000),502)]
  for name,response,expected in cases:
   with socket.socket() as listener:
    listener.bind(('127.0.0.1',0));listener.listen(1);port=listener.getsockname()[1]
    def serve():
     try:
      conn,_=listener.accept()
      with conn:
       def read(n):
        b=b''
        while len(b)<n:
         chunk=conn.recv(n-len(b))
         if not chunk:raise EOFError()
         b+=chunk
        return b
       while True:
        h=struct.unpack('!BBHHBB',read(8));read(h[3]+h[4])
        if h[1]==5 and h[3]==0:break
       stride=7 if expected==201 else 16000
       for at in range(0,len(response),stride):conn.sendall(response[at:at+stride])
     except (BrokenPipeError,ConnectionResetError,EOFError):pass
    worker=threading.Thread(target=serve,daemon=True);worker.start();r=subprocess.run([a.php,str(script),f'tcp://127.0.0.1:{port}'],capture_output=True,text=True,check=True,timeout=10);worker.join(timeout=2);out=json.loads(r.stdout)
    if (out.get('status') if expected==201 else out.get('error'))!=expected:raise AssertionError((name,out))
    if expected==201 and (out['body']!='{}' or len([h for h in out['headers'] if h[0]=='set-cookie'])!=2):raise AssertionError(name)
    checks.append(name);print('PASS',name,flush=True)
 result={'suite':'FastCGI hostile peer','checks':len(checks),'passed':checks}
 if a.report:a.report.parent.mkdir(parents=True,exist_ok=True);a.report.write_text(json.dumps(result,indent=2)+'\n')
if __name__=='__main__':main()

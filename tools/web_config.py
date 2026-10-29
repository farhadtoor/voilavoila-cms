#!/usr/bin/env python3
"""Generate a reviewed Nginx virtual host for a root or subdirectory deployment."""
import argparse,re
from pathlib import Path

def nginx(root,mount,socket,server):
    if not re.fullmatch(r'(?:/[a-zA-Z0-9_-]+)*',mount):raise ValueError('Use a simple mount path, e.g. /cms')
    for value in (root,socket,server):
        if not re.fullmatch(r'[a-zA-Z0-9_./:@-]+',value):raise ValueError('Unsafe configuration value')
    # root is the parent DocumentRoot when mount is nonempty; never use an alias here.
    p=re.escape(mount);base=mount or '';index=base+'/index.php'
    headers='''add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=(), payment=()" always;'''
    php=f'''include fastcgi_params;
        fastcgi_pass unix:{socket};
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTP_PROXY "";
        fastcgi_hide_header X-Powered-By;
        fastcgi_intercept_errors off;'''
    outside='location / { return 404; }' if mount else ''
    return f'''# VoilaVoila CMS 1.5.1. Add TLS in the hosting configuration before exposing admin.
server {{
    listen 80;
    server_name {server};
    root {root};
    index index.php;
    server_tokens off;
    autoindex off;
    client_max_body_size 70m;
    {headers}
    location ~ (^|/)\\.(?!well-known(?:/|$)) {{ return 404; }}
    location ~* ^{p}/(?:core|storage|bin|tests|tools|sdk|docs|scripts|files|work)(?:/|$) {{ return 404; }}
    location ~* ^{p}/content/plugins/[^/]+/(?:bin|lib|engine|vendor|docs|tests|runtime)(?:/|$) {{ return 404; }}
    location = {base}/robots.txt {{ default_type text/plain; try_files $uri =404; }}
    location = {base}/security.txt {{ default_type text/plain; try_files $uri =404; }}
    location = {base}/.well-known/security.txt {{ default_type text/plain; try_files $uri =404; }}
    location ~ ^{p}/\\.well-known/acme-challenge/[A-Za-z0-9_-]+$ {{ default_type text/plain; try_files $uri =404; }}
    location ~ ^{p}/(?:index|admin|login|media|install)\\.php$ {{
        try_files $uri =404;
        {php}
    }}
    location ~* \\.(?:php[0-9]*|phtml|phar)(?:$|[/.]) {{ return 404; }}
    location ~* \\.(?:json|lock|md|yml|yaml|txt|log|sql|sqlite[0-9]*|db|bak|old|orig|dist|ini|sh|command|py|cjs|zip|tar|gz|map|conf)$ {{ return 404; }}
    location ~* ^{p}/(?:Dockerfile|LICENSE|NOTICE|Makefile|Vagrantfile|INSTALLATION\\.html)$ {{ return 404; }}
    location ~* ^{p}/(?:assets|content/(?:plugins|themes)/[^/]+)/.*\\.svg$ {{
        {headers}
        add_header Content-Security-Policy "default-src 'none'; style-src 'unsafe-inline'; sandbox" always;
        try_files $uri =404;
        expires 1h;
    }}
    location ~* ^{p}/(?:assets|content/(?:plugins|themes)/[^/]+)/.*\\.(?:css|js|png|jpe?g|gif|webp|ico|woff2?)$ {{
        try_files $uri =404;
        expires 1h;
    }}
    location {base}/ {{ rewrite ^ {index} last; }}
    {outside}
}}
'''

if __name__=='__main__':
    a=argparse.ArgumentParser(description=__doc__);a.add_argument('--root',default='/var/www/voilavoila-cms');a.add_argument('--mount',default='');a.add_argument('--socket',default='/run/php/php8.3-fpm.sock');a.add_argument('--server',default='example.test');a.add_argument('--output',type=Path);args=a.parse_args()
    text=nginx(args.root,args.mount.rstrip('/'),args.socket,args.server)
    if args.output:args.output.write_text(text)
    else:print(text,end='')

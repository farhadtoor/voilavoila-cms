"""Small urllib test client; no third-party Python dependencies."""
import http.cookiejar as cookiejar
import io
import types
import urllib.error
import urllib.parse
import urllib.request
import uuid


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class Session:
    def __init__(self):
        self.cookies = cookiejar.CookieJar()

    def get(self, url, **kwargs):
        return self.request('GET', url, **kwargs)

    def post(self, url, **kwargs):
        return self.request('POST', url, **kwargs)

    def request(self, method, url, params=None, data=None, files=None,
                headers=None, allow_redirects=True):
        if params:
            url += ('&' if '?' in url else '?') + urllib.parse.urlencode(params)
        headers = dict(headers or {})
        body = None
        if files:
            boundary = 'test-' + uuid.uuid4().hex
            stream = io.BytesIO()
            for key, value in (data or {}).items():
                stream.write(('--' + boundary + '\r\nContent-Disposition: form-data; name="' + key + '"\r\n\r\n' + str(value) + '\r\n').encode())
            for key, (name, content, mime) in files.items():
                stream.write(('--' + boundary + '\r\nContent-Disposition: form-data; name="' + key + '"; filename="' + name + '"\r\nContent-Type: ' + mime + '\r\n\r\n').encode())
                stream.write(content.encode('utf-8') if isinstance(content, str) else content)
                stream.write(b'\r\n')
            stream.write(('--' + boundary + '--\r\n').encode())
            body = stream.getvalue()
            headers['Content-Type'] = 'multipart/form-data; boundary=' + boundary
        elif data is not None:
            body = urllib.parse.urlencode(data).encode()
            headers['Content-Type'] = 'application/x-www-form-urlencoded'
        req = urllib.request.Request(url, data=body, headers=headers, method=method)
        handlers = [urllib.request.HTTPCookieProcessor(self.cookies)]
        if not allow_redirects:
            handlers.append(NoRedirect())
        opener = urllib.request.build_opener(*handlers)
        try:
            reply = opener.open(req, timeout=20)
        except urllib.error.HTTPError as error:
            reply = error
        with reply:
            content = reply.read()
            return types.SimpleNamespace(status_code=reply.code, headers=reply.headers,
                url=reply.url, content=content, text=content.decode('utf-8', errors='replace'),
                request=types.SimpleNamespace(headers=dict(req.header_items())))


http = types.SimpleNamespace(Session=Session, get=lambda url, **kwargs: Session().get(url, **kwargs))

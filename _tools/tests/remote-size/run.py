"""Run real cURL probes against local fixtures; never contacts external servers."""
import argparse
import json
import os
from pathlib import Path
import subprocess
import tempfile
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import urlparse

parser = argparse.ArgumentParser()
parser.add_argument('--php', default=os.environ.get('PHP_BINARY', 'php'))
parser.add_argument('--installed', help='Optional installed Joomla root to test instead of this checkout')
args = parser.parse_args()
lock = threading.Lock()

with tempfile.TemporaryDirectory(prefix='flexi-remote-size-') as temp:
    log = Path(temp) / 'requests.jsonl'
    log.write_text('')

    class Handler(BaseHTTPRequestHandler):
        protocol_version = 'HTTP/1.1'
        def log_message(self, *args):
            pass
        def do_HEAD(self):
            self.respond()
        def do_GET(self):
            self.respond()
        def respond(self):
            with lock:
                with log.open('a') as stream:
                    stream.write(json.dumps({'method': self.command, 'path': self.path, 'port': self.server.server_port}) + '\n')
            path = urlparse(self.path).path
            code, headers = 200, {'Content-Length': '12345'}
            if path == '/redirect.txt':
                code, headers = 302, {'Location': '/size.txt'}
            elif path == '/other.txt':
                code, headers = 302, {'Location': f'http://127.0.0.2:{other.server_port}/size.txt'}
            elif path == '/port.txt':
                code, headers = 302, {'Location': f'http://127.0.0.1:{port_server.server_port}/size.txt'}
            elif path == '/loop.txt':
                code, headers = 302, {'Location': '/loop.txt'}
            elif path == '/missing.txt':
                code, headers = 404, {}
            elif path == '/zero.txt':
                headers = {'Content-Length': '0'}
            elif path == '/unknown.txt':
                headers = {}
            elif path in ('/range.txt', '/nohead.txt', '/ignored.txt'):
                if self.command == 'HEAD':
                    code, headers = (405 if path == '/nohead.txt' else 200), {}
                elif path == '/ignored.txt':
                    headers = {'Content-Length': '2000000'}
                else:
                    code, headers = 206, {'Content-Range': 'bytes 0-0/67890', 'Content-Length': '1'}
            elif path == '/delayed-redirect.txt':
                time.sleep(0.3)
                code, headers = 302, {'Location': '/short-delay.txt'}
            elif path == '/short-delay.txt':
                time.sleep(0.3)
            elif path == '/range-redirect.txt':
                code, headers = (200, {}) if self.command == 'HEAD' else (302, {'Location': f'http://127.0.0.2:{other.server_port}/size.txt'})
            elif path == '/slow.txt':
                time.sleep(6)
            self.send_response(code)
            for key, value in headers.items():
                self.send_header(key, value)
            self.send_header('Connection', 'close')
            self.end_headers()
            if self.command == 'GET' and path in ('/range.txt', '/nohead.txt', '/ignored.txt'):
                try:
                    self.wfile.write(b'x' * (65536 if path == '/ignored.txt' else 1))
                except (BrokenPipeError, ConnectionResetError):
                    pass

    primary = ThreadingHTTPServer(('127.0.0.1', 0), Handler)
    other = ThreadingHTTPServer(('127.0.0.2', 0), Handler)
    port_server = ThreadingHTTPServer(('127.0.0.1', 0), Handler)
    for server in (primary, other, port_server):
        threading.Thread(target=server.serve_forever, daemon=True).start()
    env = os.environ.copy()
    env.update(FLEXI_TEST_URL=f'http://127.0.0.1:{primary.server_port}', FLEXI_TEST_OTHER=str(other.server_port), FLEXI_TEST_LOG=str(log))
    if args.installed:
        env['FLEXI_INSTALLED_ROOT'] = str(Path(args.installed).resolve())
    run = str(Path(__file__).with_name('run.php'))
    try:
        for flags in ([], ['-n']):
            result = subprocess.run([args.php, *flags, run, *(['--no-curl'] if flags else [])], env=env, timeout=45)
            if result.returncode:
                raise SystemExit(result.returncode)
    finally:
        primary.shutdown()
        other.shutdown()
        port_server.shutdown()

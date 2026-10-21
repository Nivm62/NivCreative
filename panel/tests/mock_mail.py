#!/usr/bin/env python3
"""Tiny IMAP + SMTP server used only by the e2e tests (plain TCP, one mailbox).
   python3 tests/mock_mail.py <imap_port> <smtp_port> <smtp_out.json>"""
import socketserver, sys, threading, json, base64, re

USER, PASS = 'box@example.test', 'Pw123456'
B64 = lambda s: base64.b64encode(s.encode()).decode()
MSGS = {
 'INBOX': [
  {'flags': [], 'raw': ('From: Buyer <buyer@example.com>\r\nTo: box@example.test\r\nSubject: Hello there\r\nDate: Mon, 05 Oct 2026 10:00:00 +0300\r\nMessage-ID: <m1@example.com>\r\nContent-Type: text/plain; charset=utf-8\r\n\r\nHi, see https://example.com/page please.\r\n').encode()},
  {'flags': ['\\Seen'], 'raw': ('From: =?UTF-8?B?%s?= <shir@fashion.example>\r\nTo: box@example.test\r\nSubject: =?UTF-8?B?%s?=\r\nDate: Tue, 06 Oct 2026 11:00:00 +0300\r\nMessage-ID: <m2@fashion.example>\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary="XX"\r\n\r\n--XX\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: base64\r\n\r\n%s\r\n--XX\r\nContent-Type: application/pdf; name="inv.pdf"\r\nContent-Disposition: attachment; filename="inv.pdf"\r\nContent-Transfer-Encoding: base64\r\n\r\n%s\r\n--XX--\r\n' % (B64('שיר'), B64('חשבונית'), B64('שלום, מצורפת חשבונית'), B64('PDFDATA'))).encode()},
  {'flags': [], 'raw': ('From: news@shop.example\r\nTo: box@example.test\r\nSubject: Sale\r\nDate: Wed, 07 Oct 2026 12:00:00 +0300\r\nMessage-ID: <m3@shop.example>\r\nContent-Type: text/html; charset=windows-1255\r\n\r\n<div>'.encode() + 'שלום'.encode('windows-1255') + b'</div><script>alert(1)</script><img src=x onerror=alert(2)>\r\n')},
 ],
 'Sent': [], 'Trash': [],
}
LOCK = threading.Lock()

def send_lit(w, data): w.write(('{%d}\r\n' % len(data)).encode() + data)

class Imap(socketserver.StreamRequestHandler):
    def handle(self):
        r, w = self.rfile, self.wfile
        w.write(b'* OK mock IMAP ready\r\n'); w.flush()
        auth, sel = False, None
        while True:
            line = r.readline()
            if not line: return
            m = re.match(r'(\S+) (\S+)\s*(.*?)\r?\n$', line.decode('utf8', 'replace'), re.S)
            if not m: return
            tag, cmd, arg = m.group(1), m.group(2).upper(), m.group(3)
            def ok(msg='done'): w.write(('%s OK %s\r\n' % (tag, msg)).encode()); w.flush()
            def no(msg): w.write(('%s NO %s\r\n' % (tag, msg)).encode()); w.flush()
            with LOCK:
                if cmd == 'LOGIN':
                    mm = re.match(r'"((?:[^"\\]|\\.)*)" "((?:[^"\\]|\\.)*)"', arg)
                    if mm and mm.group(1) == USER and mm.group(2) == PASS: auth = True; ok('logged in')
                    else: no('[AUTHENTICATIONFAILED] Invalid credentials')
                elif not auth: no('login first')
                elif cmd == 'LIST':
                    for f in MSGS: w.write(('* LIST (\\HasNoChildren%s) "/" "%s"\r\n' % ({'Sent': ' \\Sent', 'Trash': ' \\Trash'}.get(f, ''), f)).encode())
                    ok()
                elif cmd == 'SELECT':
                    name = arg.strip('"')
                    if name in MSGS: sel = name; w.write(('* %d EXISTS\r\n* OK [UIDVALIDITY 7] ok\r\n' % len(MSGS[name])).encode()); ok('[READ-WRITE] selected')
                    else: no('no such folder')
                elif cmd == 'UID':
                    sub, _, rest = arg.partition(' ')
                    sub = sub.upper(); box = MSGS[sel]
                    if sub == 'SEARCH':
                        res = []
                        txt = re.findall(r'"((?:[^"\\]|\\.)*)"', rest)
                        for i, mm in enumerate(box, 1):
                            if 'UNSEEN' in rest and '\\Seen' in mm['flags']: continue
                            if txt and txt[0].lower().encode() not in mm['raw'].lower(): continue
                            res.append(str(i))
                        w.write(('* SEARCH %s\r\n' % ' '.join(res)).encode()); ok()
                    elif sub == 'FETCH':
                        spec, _, items = rest.partition(' ')
                        uids = [int(x) for x in spec.split(',') if x.isdigit()]
                        for u in uids:
                            if u < 1 or u > len(box): continue
                            mm = box[u - 1]
                            out = b'* %d FETCH (UID %d' % (u, u)
                            if 'FLAGS' in items: out += b' FLAGS (' + ' '.join(mm['flags']).encode() + b')'
                            if 'RFC822.SIZE' in items: out += b' RFC822.SIZE %d' % len(mm['raw'])
                            if 'HEADER.FIELDS' in items:
                                hdr = mm['raw'].split(b'\r\n\r\n')[0] + b'\r\n\r\n'
                                out += b' BODY[HEADER.FIELDS (FROM TO CC SUBJECT DATE MESSAGE-ID)] {%d}\r\n' % len(hdr) + hdr
                            if 'BODY.PEEK[]' in items:
                                out += b' BODY[] {%d}\r\n' % len(mm['raw']) + mm['raw']
                            w.write(out + b')\r\n')
                        ok()
                    elif sub == 'STORE':
                        spec, _, flags = rest.partition(' ')
                        for u in [int(x) for x in spec.split(',') if x.isdigit()]:
                            mm = box[u - 1]
                            if '+FLAGS' in flags:
                                for f in re.findall(r'\\\w+', flags):
                                    if f not in mm['flags']: mm['flags'].append(f)
                            elif '-FLAGS' in flags:
                                for f in re.findall(r'\\\w+', flags):
                                    if f in mm['flags']: mm['flags'].remove(f)
                        ok()
                    elif sub == 'COPY':
                        spec, _, dest = rest.partition(' ')
                        dest = dest.strip('"')
                        for u in [int(x) for x in spec.split(',') if x.isdigit()]: MSGS[dest].append({'flags': [], 'raw': box[u - 1]['raw']})
                        ok()
                    else: no('unsupported')
                elif cmd == 'EXPUNGE':
                    MSGS[sel][:] = [m for m in MSGS[sel] if '\\Deleted' not in m['flags']]; ok()
                elif cmd == 'APPEND':
                    mm = re.match(r'"([^"]+)"(?: \(([^)]*)\))? \{(\d+)\}', arg)
                    w.write(b'+ go ahead\r\n'); w.flush()
                    data = r.read(int(mm.group(3))); r.readline()
                    MSGS[mm.group(1)].append({'flags': (mm.group(2) or '').split(), 'raw': data}); ok('appended')
                elif cmd == 'LOGOUT':
                    w.write(b'* BYE bye\r\n'); ok('logged out'); return
                else: no('unknown')

class Smtp(socketserver.StreamRequestHandler):
    out = None
    def handle(self):
        r, w = self.rfile, self.wfile
        w.write(b'220 mock smtp\r\n'); w.flush()
        ok_auth, rcpts, frm = False, [], ''
        while True:
            line = r.readline()
            if not line: return
            c = line.decode('utf8', 'replace').rstrip('\r\n'); u = c.upper()
            if u.startswith('EHLO'): w.write(b'250-mock\r\n250 AUTH PLAIN\r\n')
            elif u.startswith('AUTH PLAIN'):
                parts = base64.b64decode(c.split(' ', 2)[2]).split(b'\0')
                if parts[1].decode() == USER and parts[2].decode() == PASS: ok_auth = True; w.write(b'235 ok\r\n')
                else: w.write(b'535 5.7.8 Authentication failed\r\n')
            elif not ok_auth and u.split(' ')[0] in ('MAIL', 'RCPT', 'DATA'): w.write(b'530 auth required\r\n')
            elif u.startswith('MAIL FROM'): frm = re.search(r'<([^>]*)>', c).group(1); rcpts = []; w.write(b'250 ok\r\n')
            elif u.startswith('RCPT TO'): rcpts.append(re.search(r'<([^>]*)>', c).group(1)); w.write(b'250 ok\r\n')
            elif u == 'DATA':
                w.write(b'354 go\r\n'); w.flush(); buf = []
                while True:
                    l = r.readline()
                    if l in (b'.\r\n', b''): break
                    buf.append(l[1:] if l.startswith(b'..') else l)
                with LOCK:
                    try: cur = json.load(open(Smtp.out))
                    except Exception: cur = []
                    cur.append({'from': frm, 'rcpts': rcpts, 'raw': b''.join(buf).decode('utf8', 'replace')})
                    json.dump(cur, open(Smtp.out, 'w'))
                w.write(b'250 queued\r\n')
            elif u == 'QUIT': w.write(b'221 bye\r\n'); w.flush(); return
            else: w.write(b'250 ok\r\n')
            w.flush()

class Srv(socketserver.ThreadingTCPServer): allow_reuse_address = True; daemon_threads = True

if __name__ == '__main__':
    Smtp.out = sys.argv[3]; open(Smtp.out, 'w').write('[]')
    for port, h in ((int(sys.argv[1]), Imap), (int(sys.argv[2]), Smtp)):
        threading.Thread(target=Srv(('127.0.0.1', port), h).serve_forever, daemon=True).start()
    threading.Event().wait()

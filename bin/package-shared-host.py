#!/usr/bin/env python3
"""Create a fresh, individually keyed htdocs package. Never package runtime data."""
import argparse
from datetime import datetime, timezone
import hashlib
from pathlib import Path
import secrets
import zipfile

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('output', type=Path)
args = parser.parse_args()
root = Path(__file__).resolve().parents[1]
output = args.output.resolve()
if output.is_relative_to(root):
    parser.error('Keep the private deliverable outside the source checkout.')
token = secrets.token_hex(32)
directory = 'nsc-private-' + secrets.token_hex(12)
guard = "<?php\nhttp_response_code(404);exit;\n"
profile = "<?php\nif(PHP_SAPI!=='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){http_response_code(404);exit;}\nreturn ['version'=>1,'mode'=>'protected','directory'=>'" + directory + "','setup_token_hash'=>'" + hashlib.sha256(token.encode()).hexdigest() + "'];\n"
files = []
for source in sorted((root / 'public').rglob('*')):
    relative = source.relative_to(root / 'public')
    if source.is_symlink():
        raise RuntimeError('Source symlink is not allowed: ' + str(relative))
    if not source.is_file():
        continue
    if any(part.startswith('nsc-private-') or part in {'sessions', 'var', '.git'} for part in relative.parts):
        continue
    if str(relative) in {'config/hosting.php', 'config/private.php'}:
        continue
    if relative.parts[0] == 'uploads' and (len(relative.parts) < 2 or relative.parts[1] != 'branding'):
        continue
    if source.suffix.lower() in {'.sqlite', '.sqlite3', '.db', '.sql', '.key', '.nxb', '.env', '.log'}:
        raise RuntimeError('Unexpected state file: ' + str(relative))
    files.append((source, 'htdocs/' + relative.as_posix()))
output.parent.mkdir(parents=True, exist_ok=True)
with zipfile.ZipFile(output, 'x', compression=zipfile.ZIP_DEFLATED) as archive:
    for source, name in files:
        entry = zipfile.ZipInfo(name, date_time=datetime.now(timezone.utc).timetuple()[:6])
        entry.create_system = 3
        entry.external_attr = 0o100644 << 16
        entry.compress_type = zipfile.ZIP_DEFLATED
        archive.writestr(entry, source.read_bytes())
    archive.writestr('htdocs/config/hosting.php', profile)
    archive.writestr('INSTALL-KEY.php', guard + '// PRIVATE INSTALLATION KEY — read locally; DO NOT UPLOAD.\n// ' + token + '\n')
    archive.write(root / 'docs/SHARED_HOST_DA.md', 'READ-ME-DARI.md')
    archive.write(root / 'docs/SHARED_HOST_STORAGE.md', 'PRIVATE-STORAGE-AND-MOVING.md')
    archive.write(root / 'docs/VALIDATION.md', 'VALIDATION.md')
output.chmod(0o600)
print('Created individually keyed fresh-install package; no key was printed.')
print('Files:', len(files) + 5)
print('SHA256:', hashlib.sha256(output.read_bytes()).hexdigest())

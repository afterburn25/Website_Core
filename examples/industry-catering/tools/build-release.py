"""Build one deterministic, directly installable catering module ZIP."""
from pathlib import Path
import hashlib
import json
import stat
import zipfile

root = Path(__file__).resolve().parents[1]
manifest = json.loads((root / 'addon.json').read_text())
version = manifest['version']
release = root / 'releases' / f'industry-catering-{version}.zip'
for previous in (root / 'releases').glob('*.zip'):
    if previous != release:
        previous.unlink()
with zipfile.ZipFile(release, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    for path in sorted(root.rglob('*')):
        relative = path.relative_to(root)
        if relative.parts[0] not in ('assets', 'migrations', 'src', 'views', 'docs') and relative.as_posix() not in ('addon.json', 'README.md', 'MODULE_RULES.md', 'CHANGELOG.md'):
            continue
        if path.is_symlink():
            raise RuntimeError(f'Package cannot contain symlinks: {relative}')
        if path.is_file():
            assert path.suffix.lower() != '.zip', 'Nested ZIPs are forbidden'
            info = zipfile.ZipInfo(relative.as_posix(), (1980, 1, 1, 0, 0, 0))
            info.external_attr = (stat.S_IFREG | 0o644) << 16
            info.compress_type = zipfile.ZIP_DEFLATED
            archive.writestr(info, path.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
with zipfile.ZipFile(release) as archive:
    assert archive.testzip() is None
    assert json.loads(archive.read('addon.json'))['version'] == version
assert release.stat().st_size < 1_900_000, 'Keep the module below common shared-host request limits'
digest = hashlib.sha256(release.read_bytes()).hexdigest()
latest_path = root / 'releases/LATEST.json'
latest = json.loads(latest_path.read_text())
latest.update(version=version, file=release.name, sha256=digest)
latest_path.write_text(json.dumps(latest, indent=2) + '\n')
print(f'{release.name}: {release.stat().st_size:,} bytes; SHA-256 {digest}')

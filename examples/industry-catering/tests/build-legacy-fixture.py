"""Rebuild the 0.1.23 test fixture from the pinned historical source checkout.

The repository's historical 0.1.23 ZIP is truncated and does not match LATEST.
This fixture is test-only; it does not replace or claim to be that old release.
"""
import json
from pathlib import Path
import sys
import zipfile

source = Path(sys.argv[1])
output = Path(sys.argv[2])
assert json.loads((source / 'addon.json').read_text())['version'] == '0.1.23'
with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    for path in sorted(source.rglob('*')):
        relative = path.relative_to(source)
        if path.is_file() and relative.parts[0] not in ('releases', 'tests', 'docs'):
            archive.write(path, relative.as_posix())
print('Rebuilt historical-source fixture:', output.name)

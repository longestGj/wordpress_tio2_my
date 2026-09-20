"""Package the actual custom WordPress files; no synthetic Next.js build identity."""
import hashlib
import argparse
import json
import shutil
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def entries():
    files = []
    for folder in ['wp-content/plugins/tio2-content', 'wp-content/themes/tio2-malaysia']:
        files.extend((p.relative_to(ROOT).as_posix(), hashlib.sha256(p.read_bytes()).hexdigest())
                     for p in (ROOT / folder).rglob('*') if p.is_file())
    return sorted(files)


def identity(files):
    return 'wp-' + hashlib.sha256(''.join(f'{p}\0{h}\n' for p, h in files).encode()).hexdigest()


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--evidence-dir', default='docs/verification/home')
    args = parser.parse_args()
    files = entries()
    build_id = identity(files)
    commit = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()
    # Packaging requires the custom code to be committed. Evidence can be untracked.
    for path, digest in files:
        # Git's declared text filters may normalize Windows CRLF to LF. Check
        # the filtered Git blob, while the artifact binds actual runtime bytes.
        committed = subprocess.check_output(['git', 'rev-parse', f'{commit}:{path}'], cwd=ROOT).strip()
        current = subprocess.check_output(['git', 'hash-object', path], cwd=ROOT).strip()
        assert current == committed, f'Uncommitted code: {path}'
    directory = ROOT / '.runtime/artifacts' / build_id
    for path, digest in files:
        destination = directory / path
        destination.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(ROOT / path, destination)
        assert hashlib.sha256(destination.read_bytes()).hexdigest() == digest
    (directory / 'BUILD_ID').write_text(build_id + '\n', encoding='utf-8')
    record = dict(build_id=build_id, implementation_commit=commit,
                  directory=directory.relative_to(ROOT).as_posix(), files=dict(files))
    (directory / 'artifact.json').write_text(json.dumps(record, indent=2) + '\n', encoding='utf-8')
    evidence = ROOT / args.evidence_dir
    if evidence.resolve() != ROOT.resolve() and ROOT.resolve() not in evidence.resolve().parents:
        raise RuntimeError('Evidence directory must remain inside the repository')
    evidence.mkdir(parents=True, exist_ok=True)
    (evidence / 'artifact.json').write_text(json.dumps(record, indent=2) + '\n', encoding='utf-8')
    print(build_id)

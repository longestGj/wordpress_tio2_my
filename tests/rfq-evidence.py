import json
import subprocess
import sys
import tempfile
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
SCRIPT=ROOT/"scripts"/"rfq-evidence.py"
with tempfile.TemporaryDirectory() as temporary:
    directory=Path(temporary)
    incomplete={"acceptance":[{"id":"RFQ-D32-AC-01-IDENTITY-CONTENT","status":"PASS","evidence":[],"commands":[],"result":"","ownerBoundary":""}],"dependencies":[]}
    (directory/"acceptance.json").write_text(json.dumps(incomplete),encoding="utf-8")
    result=subprocess.run([sys.executable,str(SCRIPT),"--validate-dir",str(directory)],cwd=ROOT,capture_output=True,text=True)
    assert result.returncode!=0

final=ROOT/"docs"/"verification"/"request-a-quote"
if final.is_dir() and (final/"acceptance.json").is_file():
    result=subprocess.run([sys.executable,str(SCRIPT),"--validate-dir",str(final)],cwd=ROOT,capture_output=True,text=True)
    assert result.returncode==0,result.stderr

print("RFQ evidence contract rejects incomplete mappings and validates exact final ID sets when present")

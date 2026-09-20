import json
import subprocess
import urllib.request
import urllib.error
from pathlib import Path
from support.runtime import runtime_settings, workspace_container_path

root=Path(__file__).resolve().parents[1]
runtime=runtime_settings()
out=Path(runtime['output_dir'])
def cli(*args):
    return subprocess.run(['docker','compose','--env-file',runtime['env_file'],'-f',runtime['compose_file'],'run','--rm','wpcli',*args],cwd=root,capture_output=True,text=True,encoding='utf-8',check=True).stdout
def http():
    try:
        with urllib.request.urlopen(runtime['base_url']+'/') as r:return r.status,r.read().decode()
    except urllib.error.HTTPError as r:return r.code,r.read().decode()
original=json.loads(cli('eval-file','/workspace/scripts/snapshot.php','export'))
restore_file=out/'negative-restore.json'
restore_file.write_text(json.dumps(original,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
results=[]
for mode in ['wrong-scope','missing-content','foreign-media']:
    try:
        cli('eval-file','/workspace/scripts/negative-runtime.php',mode)
        status,body=http()
        assert status==503,(mode,status)
        assert 'Site content is temporarily unavailable.' in body
        assert 'Malaysia Titanium Dioxide for Industrial Buyers' not in body
        results.append(dict(mode=mode,status=status,noCrossSiteFallback=True))
    finally:
        if mode=='foreign-media':cli('eval-file','/workspace/scripts/negative-runtime.php','restore-media')
        cli('eval-file','/workspace/scripts/snapshot.php','restore',workspace_container_path(restore_file))
    assert http()[0]==200
restored=json.loads(cli('eval-file','/workspace/scripts/snapshot.php','export'))
assert restored['content']==original['content']
assert restored['rfq_content']==original['rfq_content']
(out/'negative-runtime.json').write_text(json.dumps(dict(cases=results,restoredExactly=True),indent=2)+'\n',encoding='utf-8')
print('Three actual runtime isolation failure/restore scenarios passed')

#!/usr/bin/env python3
"""Exercise Forms desktop/mobile/no-JS in the isolated 1.3 fixture."""
import argparse,os,subprocess,sys
from pathlib import Path
p=argparse.ArgumentParser(description=__doc__);p.add_argument('--php',default='php');p.add_argument('--report',type=Path,required=True);a=p.parse_args();a.report.absolute().parent.mkdir(parents=True,exist_ok=True)
subprocess.run([sys.executable,str(Path(__file__).with_name('release_130.py')),'--php',a.php],env={**os.environ,'VV_FORMS_BROWSER_REPORT':str(a.report.absolute())},check=True)

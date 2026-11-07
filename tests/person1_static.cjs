const fs = require('fs');
const cp = require('child_process');
// ripgrep when installed; otherwise git's tracked + untracked (non-ignored) files.
let listing;
try { listing = cp.execFileSync('rg', ['--files','--hidden','-g','!.git/**','-g','!tests/.runtime/**'], {encoding:'utf8'}); }
catch (e) { if (e.code !== 'ENOENT') throw e; listing = cp.execFileSync('git', ['ls-files','--cached','--others','--exclude-standard'], {encoding:'utf8'}); }
const files = listing.split(/\r?\n/).filter(f => f && !f.startsWith('tests/.runtime/') && fs.existsSync(f));
const result = {php:{checks:0,failures:[]},javascript:{checks:0,failures:[]},secret_scan:{files:0,findings:[]}};
for (const file of files) {
  if (file.endsWith('.php')) {
    result.php.checks++;
    const check = cp.spawnSync('php',['-l',file],{encoding:'utf8'});
    if (check.status !== 0) result.php.failures.push({file,error:check.stdout+check.stderr});
  }
  if (/\.(?:js|cjs)$/.test(file)) {
    result.javascript.checks++;
    const check = cp.spawnSync('node',['--check',file],{encoding:'utf8'});
    if (check.status !== 0) result.javascript.failures.push({file,error:check.stderr});
  }
  if (/\.(?:php|js|cjs|ps1|md|json|sql)$/.test(file) || file === '.env.example') {
    result.secret_scan.files++;
    const text = fs.readFileSync(file,'utf8');
    for (const pattern of [/postgres(?:ql)?:\/\/[^\s:]+:[^\s@]+@/g, /(?:re_|sk-proj-)[A-Za-z0-9_-]{24,}/g]) {
      if (pattern.test(text)) result.secret_scan.findings.push({file,pattern:'Potential embedded credential (value suppressed)'});
    }
  }
}
fs.writeFileSync('docs/person1-static-results.json',JSON.stringify(result,null,2));
console.log(JSON.stringify(result));
if(result.php.failures.length || result.javascript.failures.length || result.secret_scan.findings.length)process.exitCode=1;

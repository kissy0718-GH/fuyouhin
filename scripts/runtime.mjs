import {runCLI} from '@wp-playground/cli';
import {mkdir,readFile} from 'node:fs/promises';
import path from 'node:path';
export async function boot({port=9400,storage='wordpress'}={}){
 const root=process.cwd();const directory=path.join(root,'.runtime',storage);await mkdir(directory,{recursive:true});
 const blueprint={preferredVersions:{php:'8.3',wp:'6.9'},constants:{PORTAL_DEMO_MODE:true,WP_DEBUG:true,WP_DEBUG_DISPLAY:false,DISABLE_WP_CRON:true},steps:[
  {step:'activatePlugin',pluginPath:'/wordpress/wp-content/plugins/portal-core/portal-core.php'},
  {step:'activateTheme',themeFolderName:'honest-portal'},
  {step:'runPHP',code:await readFile(path.join(root,'scripts/seed.php'),'utf8')}
 ]};
 return runCLI({command:'server',port,php:'8.3',wp:'6.9',login:false,workers:1,'site-url':`http://127.0.0.1:${port}`,blueprint,
  'mount-before-install':[{hostPath:directory,vfsPath:'/wordpress'}],
  mount:[{hostPath:path.join(root,'wordpress/wp-content/plugins/portal-core'),vfsPath:'/wordpress/wp-content/plugins/portal-core'},{hostPath:path.join(root,'wordpress/wp-content/themes/honest-portal'),vfsPath:'/wordpress/wp-content/themes/honest-portal'}]
 });
}

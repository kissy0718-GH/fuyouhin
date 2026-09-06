import {boot} from './runtime.mjs';
const server=await boot();
console.log(`MVP ready: ${server.serverUrl}`);
console.log(`Admin: ${server.serverUrl}/wp-admin/admin.php?page=portal-dashboard`);
for(const signal of ['SIGINT','SIGTERM'])process.on(signal,async()=>{await server[Symbol.asyncDispose]();process.exit(0);});

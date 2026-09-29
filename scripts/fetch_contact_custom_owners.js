import 'dotenv/config';
import jsforce from 'jsforce';
import fs from 'fs';
import path from 'path';

const loginUrl = process.env.SF_LOGIN_URL || process.env.SALESFORCE_LOGIN_URL || 'https://login.salesforce.com';
const username = process.env.SF_USERNAME || process.env.SALESFORCE_USERNAME;
const password = process.env.SF_PASSWORD || process.env.SALESFORCE_PASSWORD;

async function run() {
    const outputFile = process.argv[2] || path.join(process.cwd(), 'storage/app/salesforce/contact_custom_owners.jsonl');
    const dir = path.dirname(outputFile);
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }

    const conn = new jsforce.Connection({ loginUrl });
    await conn.login(username, password);

    const writeStream = fs.createWriteStream(outputFile, { encoding: 'utf8' });
    let count = 0;

    await new Promise((resolve, reject) => {
        conn.query("SELECT Id, Custom_Owner__c FROM Contact")
            .on("record", (record) => {
                count++;
                const item = {
                    salesforce_id: record.Id,
                    Custom_Owner__c: record.Custom_Owner__c || null
                };
                writeStream.write(JSON.stringify(item) + "\n");
            })
            .on("end", () => {
                writeStream.end(() => resolve());
            })
            .on("error", (err) => {
                writeStream.end();
                reject(err);
            })
            .run({ autoFetch: true, maxFetch: 100000 });
    });

    console.log(JSON.stringify({ success: true, count, outputFile }));
}

run().catch(err => {
    console.error(JSON.stringify({ success: false, error: err.message }));
    process.exit(1);
});

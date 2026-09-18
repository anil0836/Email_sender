import jsforce from 'jsforce';

/**
 * Parse CLI arguments
 */
function parseArgs() {
    const args = process.argv.slice(2);
    const params = {
        since: null,
        limit: null,
        full: false,
    };

    for (let i = 0; i < args.length; i++) {
        if (args[i] === '--since' && args[i + 1]) {
            params.since = args[i + 1].trim();
            i++;
        } else if (args[i] === '--limit' && args[i + 1]) {
            params.limit = parseInt(args[i + 1], 10) || null;
            i++;
        } else if (args[i] === '--full') {
            params.full = true;
        }
    }
    return params;
}

function sanitizeErrorMessage(err, loginUrl, username, password) {
    const rawMsg = err.message || String(err);
    let friendlyMsg = rawMsg;

    if (rawMsg.includes('INVALID_LOGIN') || rawMsg.includes('authentication failure')) {
        friendlyMsg = 'Salesforce authentication failed: Invalid username, password, or security token.';
    } else if (rawMsg.includes('LOGIN_MUST_USE_SECURITY_TOKEN')) {
        friendlyMsg = 'Salesforce requires a Security Token: Append your Salesforce security token to SF_PASSWORD in your .env file.';
    } else if (rawMsg.includes('ENOTFOUND') || rawMsg.includes('fetch failed')) {
        friendlyMsg = `Unable to connect to Salesforce login endpoint (${loginUrl}). Please check network connectivity.`;
    }

    if (username) friendlyMsg = friendlyMsg.split(username).join('***');
    if (password) friendlyMsg = friendlyMsg.split(password).join('***');

    return friendlyMsg;
}

async function main() {
    const { since, limit, full } = parseArgs();

    const loginUrl = process.env.SF_LOGIN_URL || process.env.SALESFORCE_LOGIN_URL || 'https://login.salesforce.com';
    const username = process.env.SF_USERNAME || process.env.SALESFORCE_USERNAME || '';
    const password = process.env.SF_PASSWORD || process.env.SALESFORCE_PASSWORD || '';

    if (!username.trim() || !password.trim()) {
        console.log(JSON.stringify({
            success: false,
            error: 'Salesforce credentials missing in environment.',
            records: []
        }));
        process.exit(0);
    }

    try {
        const conn = new jsforce.Connection({
            loginUrl: loginUrl.trim(),
            version: '61.0'
        });

        await conn.login(username.trim(), password.trim());

        // Build SOQL Query
        const fields = [
            'Id',
            'FirstName',
            'LastName',
            'Name',
            'Company',
            'Title',
            'Email',
            'Phone',
            'MobilePhone',
            'Website',
            'LeadSource',
            'Industry',
            'Status',
            'Street',
            'City',
            'State',
            'PostalCode',
            'Country',
            'OwnerId',
            'CreatedDate',
            'LastModifiedDate'
        ];

        let whereClause = '';
        if (!full && since) {
            // ISO 8601 string for SOQL (e.g. 2026-09-16T12:00:00Z)
            const cleanSince = since.includes('T') ? since : `${since}T00:00:00Z`;
            whereClause = ` WHERE LastModifiedDate > ${cleanSince}`;
        }

        let limitClause = '';
        if (limit && limit > 0) {
            limitClause = ` LIMIT ${limit}`;
        }

        const soql = `SELECT ${fields.join(', ')} FROM Lead${whereClause} ORDER BY LastModifiedDate ASC${limitClause}`.trim();

        const records = [];
        let maxLastModified = null;

        await new Promise((resolve, reject) => {
            const query = conn.query(soql)
                .on('record', (r) => {
                    const mapped = {
                        salesforce_id: r.Id || '',
                        first_name: r.FirstName || '',
                        last_name: r.LastName || '',
                        name: r.Name || `${r.FirstName || ''} ${r.LastName || ''}`.trim() || '',
                        company: r.Company || '',
                        title: r.Title || '',
                        email: r.Email || '',
                        phone: r.Phone || '',
                        mobile_phone: r.MobilePhone || '',
                        website: r.Website || '',
                        lead_source: r.LeadSource || '',
                        industry: r.Industry || '',
                        status: r.Status || 'New',
                        street: r.Street || '',
                        city: r.City || '',
                        state: r.State || '',
                        postal_code: r.PostalCode || '',
                        country: r.Country || '',
                        owner_id: r.OwnerId || '',
                        salesforce_created_at: r.CreatedDate || null,
                        salesforce_updated_at: r.LastModifiedDate || null,
                    };

                    if (r.LastModifiedDate) {
                        if (!maxLastModified || r.LastModifiedDate > maxLastModified) {
                            maxLastModified = r.LastModifiedDate;
                        }
                    }

                    records.push(mapped);
                })
                .on('end', () => resolve())
                .on('error', (err) => reject(err))
                .run({ autoFetch: true, maxFetch: limit || 100000 });
        });

        console.log(JSON.stringify({
            success: true,
            totalFetched: records.length,
            lastModifiedCheckpoint: maxLastModified,
            records: records
        }));
    } catch (err) {
        const friendlyError = sanitizeErrorMessage(err, loginUrl, username, password);
        console.log(JSON.stringify({
            success: false,
            error: friendlyError,
            records: []
        }));
    }
}

main().catch(err => {
    console.log(JSON.stringify({
        success: false,
        error: 'Execution error: ' + (err.message || String(err)),
        records: []
    }));
});

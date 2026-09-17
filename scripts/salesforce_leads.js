import jsforce from 'jsforce';

/**
 * Parse CLI arguments into key-value map
 */
function parseArgs() {
    const args = process.argv.slice(2);
    const params = {
        limit: 25,
        offset: 0,
        search: ''
    };

    for (let i = 0; i < args.length; i++) {
        if (args[i] === '--limit' && args[i + 1]) {
            params.limit = Math.max(1, Math.min(100, parseInt(args[i + 1], 10) || 25));
            i++;
        } else if (args[i] === '--offset' && args[i + 1]) {
            params.offset = Math.max(0, parseInt(args[i + 1], 10) || 0);
            i++;
        } else if (args[i] === '--search' && args[i + 1]) {
            params.search = args[i + 1].trim();
            i++;
        }
    }
    return params;
}

/**
 * Sanitize and categorize Salesforce error messages
 */
function sanitizeErrorMessage(err, loginUrl, username, password) {
    const rawMsg = err.message || String(err);
    let friendlyMsg = rawMsg;

    if (rawMsg.includes('INVALID_LOGIN') || rawMsg.includes('authentication failure')) {
        friendlyMsg = 'Salesforce authentication failed: Invalid username, password, or security token. Ensure SF_PASSWORD contains your password immediately followed by your Salesforce Security Token.';
    } else if (rawMsg.includes('LOGIN_MUST_USE_SECURITY_TOKEN')) {
        friendlyMsg = 'Salesforce requires a Security Token: Append your Salesforce security token to SF_PASSWORD in your .env file.';
    } else if (rawMsg.includes('INVALID_OPERATION_WITH_EXPIRED_PASSWORD') || rawMsg.includes('PASSWORD_EXPIRED')) {
        friendlyMsg = 'Salesforce password has expired. Please log into Salesforce directly and reset your password.';
    } else if (rawMsg.includes('INVALID_FIELD') || rawMsg.includes('No such column')) {
        friendlyMsg = 'Salesforce SOQL error: One or more requested fields are not accessible or do not exist on the Lead object in this organization.';
    } else if (rawMsg.includes('NOT_FOUND') || rawMsg.includes('sObject type') || rawMsg.includes('INVALID_TYPE')) {
        friendlyMsg = 'Salesforce Lead object is not accessible with the current user permissions.';
    } else if (rawMsg.includes('ENOTFOUND') || rawMsg.includes('ECONNREFUSED') || rawMsg.includes('ETIMEDOUT') || rawMsg.includes('fetch failed')) {
        friendlyMsg = `Unable to connect to Salesforce login endpoint (${loginUrl || 'https://login.salesforce.com'}). Please check your network connection and SF_LOGIN_URL.`;
    }

    // Mask credentials in case they appeared anywhere in the error message
    if (username) {
        friendlyMsg = friendlyMsg.split(username).join('***');
    }
    if (password) {
        friendlyMsg = friendlyMsg.split(password).join('***');
    }

    return friendlyMsg;
}

async function main() {
    const { limit, offset, search } = parseArgs();

    const loginUrl = process.env.SF_LOGIN_URL || 'https://login.salesforce.com';
    const username = process.env.SF_USERNAME || '';
    const password = process.env.SF_PASSWORD || '';

    // Check credential presence
    if (!username.trim() || !password.trim()) {
        const response = {
            success: false,
            error: 'Salesforce credentials are not configured. Please set SF_USERNAME and SF_PASSWORD in your .env file.',
            errorCode: 'CREDENTIALS_MISSING',
            total: 0,
            limit,
            offset,
            records: []
        };
        console.log(JSON.stringify(response));
        process.exit(0);
    }

    try {
        // Initialize JSforce Connection
        const conn = new jsforce.Connection({
            loginUrl: loginUrl.trim()
        });

        // Authenticate with Salesforce
        await conn.login(username.trim(), password.trim());

        // Build SOQL Query
        let whereClause = '';
        if (search) {
            // Escape single quotes for SOQL injection prevention
            const escapedSearch = search.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
            whereClause = ` WHERE Name LIKE '%${escapedSearch}%' OR Company LIKE '%${escapedSearch}%' OR Email LIKE '%${escapedSearch}%' OR Status LIKE '%${escapedSearch}%'`;
        }

        // 1. Fetch total count
        let totalCount = 0;
        try {
            const countQuery = `SELECT COUNT() FROM Lead${whereClause}`;
            const countResult = await conn.query(countQuery);
            totalCount = countResult.totalSize || 0;
        } catch (countErr) {
            // Fallback: If COUNT() query fails or restricted, proceed with record count
            totalCount = 0;
        }

        // 2. Fetch paginated Lead records
        const soql = `
            SELECT
                Id,
                Name,
                FirstName,
                LastName,
                Company,
                Title,
                Email,
                Phone,
                MobilePhone,
                Status,
                LeadSource,
                OwnerId,
                CreatedDate
            FROM Lead
            ${whereClause}
            ORDER BY CreatedDate DESC
            LIMIT ${limit}
            OFFSET ${offset}
        `.trim();

        const queryResult = await conn.query(soql);
        const rawRecords = queryResult.records || [];

        // If totalCount was 0 or not returned by COUNT(), estimate from queryResult if under limit
        if (totalCount === 0) {
            totalCount = queryResult.totalSize || rawRecords.length;
        }

        // Sanitize and format records
        const records = rawRecords.map(r => ({
            Id: r.Id || '',
            Name: r.Name || (r.FirstName ? `${r.FirstName} ${r.LastName || ''}`.trim() : (r.LastName || 'Unknown')),
            FirstName: r.FirstName || '',
            LastName: r.LastName || '',
            Company: r.Company || '',
            Title: r.Title || '',
            Email: r.Email || '',
            Phone: r.Phone || '',
            MobilePhone: r.MobilePhone || '',
            Status: r.Status || 'New',
            LeadSource: r.LeadSource || '',
            OwnerId: r.OwnerId || '',
            CreatedDate: r.CreatedDate || ''
        }));

        const response = {
            success: true,
            total: totalCount,
            limit,
            offset,
            page: Math.floor(offset / limit) + 1,
            totalPages: Math.max(1, Math.ceil(totalCount / limit)),
            records
        };

        console.log(JSON.stringify(response));
    } catch (err) {
        const friendlyError = sanitizeErrorMessage(err, loginUrl, username, password);
        const response = {
            success: false,
            error: friendlyError,
            errorCode: err.name || 'SALESFORCE_ERROR',
            total: 0,
            limit,
            offset,
            records: []
        };
        console.log(JSON.stringify(response));
    }
}

main().catch(err => {
    console.log(JSON.stringify({
        success: false,
        error: 'Unexpected server-side error during Salesforce execution: ' + (err.message || String(err)),
        errorCode: 'UNEXPECTED_ERROR',
        total: 0,
        records: []
    }));
});

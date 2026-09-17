import 'dotenv/config';
import jsforce from 'jsforce';

/**
 * Parse CLI arguments
 */
function parseArgs() {
    const args = process.argv.slice(2);
    const params = {
        object: 'Lead',
        since: null,
        limit: null,
        full: false,
    };

    for (let i = 0; i < args.length; i++) {
        if (args[i] === '--object' && args[i + 1]) {
            params.object = args[i + 1].trim();
            i++;
        } else if (args[i] === '--since' && args[i + 1]) {
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

const OBJECT_CONFIGS = {
    Lead: {
        sfObjectName: 'Lead',
        fields: [
            'Id', 'FirstName', 'LastName', 'Name', 'Company', 'Title', 'Email',
            'Phone', 'MobilePhone', 'Website', 'LeadSource', 'Industry', 'Status',
            'Street', 'City', 'State', 'PostalCode', 'Country', 'OwnerId',
            'Prime_Owner__c', 'Secondary_Owner__c', 'Custom_Owner__c',
            'CreatedDate', 'LastModifiedDate'
        ],
        mapRecord: (r) => ({
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
            prime_owner_id: r.Prime_Owner__c || '',
            secondary_owner: r.Secondary_Owner__c || '',
            custom_owner: r.Custom_Owner__c || '',
            salesforce_created_at: r.CreatedDate || null,
            salesforce_updated_at: r.LastModifiedDate || null,
        })
    },
    Account: {
        sfObjectName: 'Account',
        fields: [
            'Id', 'Name', 'Type', 'Industry', 'Phone', 'Website',
            'BillingStreet', 'BillingCity', 'BillingState', 'BillingPostalCode', 'BillingCountry',
            'ShippingStreet', 'ShippingCity', 'ShippingState', 'ShippingPostalCode', 'ShippingCountry',
            'NumberOfEmployees', 'OwnerId', 'ParentId',
            'Prime_Owner__c', 'Secondary_Owner__c', 'Custom_Owner__c',
            'CreatedDate', 'LastModifiedDate'
        ],
        mapRecord: (r) => ({
            salesforce_id: r.Id || '',
            name: r.Name || '',
            type: r.Type || '',
            industry: r.Industry || '',
            phone: r.Phone || '',
            website: r.Website || '',
            billing_street: r.BillingStreet || '',
            billing_city: r.BillingCity || '',
            billing_state: r.BillingState || '',
            billing_postal_code: r.BillingPostalCode || '',
            billing_country: r.BillingCountry || '',
            shipping_street: r.ShippingStreet || '',
            shipping_city: r.ShippingCity || '',
            shipping_state: r.ShippingState || '',
            shipping_postal_code: r.ShippingPostalCode || '',
            shipping_country: r.ShippingCountry || '',
            number_of_employees: r.NumberOfEmployees ? parseInt(r.NumberOfEmployees, 10) : null,
            owner_id: r.OwnerId || '',
            prime_owner_id: r.Prime_Owner__c || '',
            secondary_owner: r.Secondary_Owner__c || '',
            custom_owner: r.Custom_Owner__c || '',
            parent_id: r.ParentId || '',
            salesforce_created_at: r.CreatedDate || null,
            salesforce_updated_at: r.LastModifiedDate || null,
        })
    },
    Contact: {
        sfObjectName: 'Contact',
        fields: [
            'Id', 'AccountId', 'FirstName', 'LastName', 'Name', 'Title', 'Department',
            'Email', 'Phone', 'MobilePhone', 'LeadSource',
            'MailingStreet', 'MailingCity', 'MailingState', 'MailingPostalCode', 'MailingCountry',
            'OwnerId', 'Prime_Owner__c', 'Secondary_Owner__c', 'CreatedDate', 'LastModifiedDate'
        ],
        mapRecord: (r) => ({
            salesforce_id: r.Id || '',
            account_id: r.AccountId || '',
            first_name: r.FirstName || '',
            last_name: r.LastName || '',
            name: r.Name || `${r.FirstName || ''} ${r.LastName || ''}`.trim() || '',
            title: r.Title || '',
            department: r.Department || '',
            email: r.Email || '',
            phone: r.Phone || '',
            mobile_phone: r.MobilePhone || '',
            lead_source: r.LeadSource || '',
            mailing_street: r.MailingStreet || '',
            mailing_city: r.MailingCity || '',
            mailing_state: r.MailingState || '',
            mailing_postal_code: r.MailingPostalCode || '',
            mailing_country: r.MailingCountry || '',
            owner_id: r.OwnerId || '',
            prime_owner_id: r.Prime_Owner__c || '',
            secondary_owner: r.Secondary_Owner__c || '',
            salesforce_created_at: r.CreatedDate || null,
            salesforce_updated_at: r.LastModifiedDate || null,
        })
    },
    User: {
        sfObjectName: 'User',
        fields: [
            'Id', 'Username', 'FirstName', 'LastName', 'Name', 'Email', 'Title',
            'Department', 'CompanyName', 'Division', 'Phone', 'MobilePhone',
            'City', 'State', 'Country', 'IsActive', 'UserType', 'ProfileId', 'UserRoleId',
            'CreatedDate', 'LastModifiedDate'
        ],
        mapRecord: (r) => ({
            salesforce_id: r.Id || '',
            username: r.Username || '',
            first_name: r.FirstName || '',
            last_name: r.LastName || '',
            name: r.Name || `${r.FirstName || ''} ${r.LastName || ''}`.trim() || '',
            email: r.Email || '',
            title: r.Title || '',
            department: r.Department || '',
            company_name: r.CompanyName || '',
            division: r.Division || '',
            phone: r.Phone || '',
            mobile_phone: r.MobilePhone || '',
            city: r.City || '',
            state: r.State || '',
            country: r.Country || '',
            is_active: r.IsActive === true || r.IsActive === 'true',
            user_type: r.UserType || '',
            profile_id: r.ProfileId || '',
            user_role_id: r.UserRoleId || '',
            salesforce_created_at: r.CreatedDate || null,
            salesforce_updated_at: r.LastModifiedDate || null,
        })
    },
    SF_User__c: {
        sfObjectName: 'SF_User__c',
        fields: [
            'Id', 'Name', 'Emp_Name__c', 'Full_Name_IN__c', 'Emp_Email__c', 'Emp_Code__c',
            'Process__c', 'Team__c', 'Location__c', 'IsActive__c',
            'DOJ__c', 'DOL__c', 'DOB__c', 'Phone__c', 'Mobile__c', 'Linkedin__c',
            'OwnerId', 'CreatedDate', 'LastModifiedDate'
        ],
        mapRecord: (r) => ({
            salesforce_id: r.Id || '',
            name: r.Name || '',
            emp_name: r.Emp_Name__c || '',
            full_name_in: r.Full_Name_IN__c || '',
            emp_email: r.Emp_Email__c || '',
            emp_code: r.Emp_Code__c !== null && r.Emp_Code__c !== undefined ? String(r.Emp_Code__c) : '',
            process: r.Process__c || '',
            team: r.Team__c || '',
            location: r.Location__c || '',
            is_active: r.IsActive__c === true || r.IsActive__c === 'true',
            doj: r.DOJ__c || null,
            dol: r.DOL__c || null,
            dob: r.DOB__c || null,
            phone: r.Phone__c || '',
            mobile: r.Mobile__c || '',
            linkedin: r.Linkedin__c || '',
            owner_id: r.OwnerId || '',
            salesforce_created_at: r.CreatedDate || null,
            salesforce_updated_at: r.LastModifiedDate || null,
        })
    }
};

// Aliases
OBJECT_CONFIGS['SfUser'] = OBJECT_CONFIGS['SF_User__c'];
OBJECT_CONFIGS['SF_User'] = OBJECT_CONFIGS['SF_User__c'];
OBJECT_CONFIGS['Sf_User'] = OBJECT_CONFIGS['SF_User__c'];

async function main() {
    const { object, since, limit, full } = parseArgs();

    let config = OBJECT_CONFIGS[object];
    if (!config) {
        const normalized = object.charAt(0).toUpperCase() + object.slice(1);
        config = OBJECT_CONFIGS[normalized];
    }

    if (!config) {
        console.log(JSON.stringify({
            success: false,
            error: `Unsupported Salesforce object: ${object}. Supported objects: Lead, Account, Contact, User, SF_User__c.`,
            records: []
        }));
        process.exit(0);
    }

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

        let whereClause = '';
        if (!full && since) {
            const cleanSince = since.includes('T') ? since : `${since}T00:00:00Z`;
            whereClause = ` WHERE LastModifiedDate > ${cleanSince}`;
        }

        let limitClause = '';
        if (limit && limit > 0) {
            limitClause = ` LIMIT ${limit}`;
        }

        const sfObj = config.sfObjectName;
        const soql = `SELECT ${config.fields.join(', ')} FROM ${sfObj}${whereClause} ORDER BY LastModifiedDate ASC${limitClause}`.trim();

        const records = [];
        let maxLastModified = null;

        await new Promise((resolve, reject) => {
            conn.query(soql)
                .on('record', (r) => {
                    const mapped = config.mapRecord(r);

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
            object: sfObj,
            totalFetched: records.length,
            lastModifiedCheckpoint: maxLastModified,
            records: records
        }));
    } catch (err) {
        const friendlyError = sanitizeErrorMessage(err, loginUrl, username, password);
        console.log(JSON.stringify({
            success: false,
            object: config ? config.sfObjectName : object,
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

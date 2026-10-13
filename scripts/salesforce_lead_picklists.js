import 'dotenv/config';
import jsforce from 'jsforce';

/**
 * Sanitize and categorize Salesforce error messages to prevent credential leaks.
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
    } else if (rawMsg.includes('NOT_FOUND') || rawMsg.includes('sObject type') || rawMsg.includes('INVALID_TYPE')) {
        friendlyMsg = 'Salesforce Lead object is not accessible with the current user permissions.';
    } else if (rawMsg.includes('ENOTFOUND') || rawMsg.includes('ECONNREFUSED') || rawMsg.includes('ETIMEDOUT') || rawMsg.includes('fetch failed')) {
        friendlyMsg = `Unable to connect to Salesforce login endpoint (${loginUrl || 'https://login.salesforce.com'}). Please check your network connection and SF_LOGIN_URL.`;
    }

    if (username) friendlyMsg = friendlyMsg.split(username).join('***');
    if (password) friendlyMsg = friendlyMsg.split(password).join('***');

    return friendlyMsg;
}

async function main() {
    const loginUrl = process.env.SF_LOGIN_URL || process.env.SALESFORCE_LOGIN_URL || 'https://login.salesforce.com';
    const username = process.env.SF_USERNAME || process.env.SALESFORCE_USERNAME || '';
    const password = process.env.SF_PASSWORD || process.env.SALESFORCE_PASSWORD || '';

    if (!username.trim() || !password.trim()) {
        console.log(JSON.stringify({
            success: false,
            error: 'Salesforce credentials are not configured. Please set SF_USERNAME and SF_PASSWORD in your .env file.',
            errorCode: 'CREDENTIALS_MISSING',
            fields: {}
        }));
        process.exit(0);
    }

    try {
        const conn = new jsforce.Connection({
            loginUrl: loginUrl.trim(),
            version: '61.0'
        });

        await conn.login(username.trim(), password.trim());

        // Describe the Salesforce Lead object metadata
        const meta = await conn.sobject('Lead').describe();

        const TARGET_FIELDS = [
            { key: 'deal_category', apiName: 'Deal_Category__c' },
            { key: 'region', apiName: 'Region__c' },
            { key: 'country', apiName: 'Country_Custom__c' }
        ];

        const fieldResults = {};

        for (const target of TARGET_FIELDS) {
            const field = (meta.fields || []).find(
                f => f.name.toLowerCase() === target.apiName.toLowerCase()
            );

            if (field) {
                // Filter only active picklist values
                let activeValues = (field.picklistValues || [])
                    .filter(pv => pv.active !== false)
                    .map(pv => ({
                        label: pv.label || pv.value,
                        value: pv.value,
                        defaultValue: Boolean(pv.defaultValue),
                        validFor: pv.validFor || null
                    }));

                // Ensure custom region options requested for Leads are included
                if (target.apiName.toLowerCase() === 'region__c') {
                    const customRegion = 'Far East Asia + Southeast Asia Countries + Latin America';
                    if (!activeValues.some(v => v.value.toLowerCase() === customRegion.toLowerCase())) {
                        activeValues.push({
                            label: customRegion,
                            value: customRegion,
                            defaultValue: false,
                            validFor: null
                        });
                    }
                    activeValues.sort((a, b) => a.label.localeCompare(b.label));
                }

                fieldResults[target.apiName] = {
                    key: target.key,
                    name: field.name,
                    label: field.label,
                    type: field.type,
                    controllerName: field.controllerName || null,
                    values: activeValues
                };
            } else {
                fieldResults[target.apiName] = {
                    key: target.key,
                    name: target.apiName,
                    label: target.apiName,
                    type: 'picklist',
                    controllerName: null,
                    values: []
                };
            }
        }

        const recordTypes = (meta.recordTypeInfos || []).map(rt => ({
            recordTypeId: rt.recordTypeId,
            name: rt.name,
            developerName: rt.developerName,
            available: rt.available,
            defaultRecordTypeMapping: rt.defaultRecordTypeMapping,
            master: rt.master
        }));

        console.log(JSON.stringify({
            success: true,
            object: 'Lead',
            fields: fieldResults,
            recordTypes: recordTypes
        }));
    } catch (err) {
        const friendlyError = sanitizeErrorMessage(err, loginUrl, username, password);
        console.log(JSON.stringify({
            success: false,
            error: friendlyError,
            errorCode: err.name || 'SALESFORCE_ERROR',
            fields: {}
        }));
    }
}

main().catch(err => {
    console.log(JSON.stringify({
        success: false,
        error: 'Unexpected server-side error during Salesforce execution: ' + (err.message || String(err)),
        errorCode: 'UNEXPECTED_ERROR',
        fields: {}
    }));
});

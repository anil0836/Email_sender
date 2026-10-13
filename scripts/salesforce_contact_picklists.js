import 'dotenv/config';
import jsforce from 'jsforce';

/**
 * Sanitize and categorize Salesforce error messages to prevent credential leaks.
 */
function sanitizeErrorMessage(err, loginUrl, username, password) {
    const rawMsg = err.message || String(err);
    let friendlyMsg = rawMsg;

    if (rawMsg.includes('INVALID_LOGIN') || rawMsg.includes('authentication failure')) {
        friendlyMsg = 'Salesforce authentication failed: Invalid username, password, or security token.';
    } else if (rawMsg.includes('LOGIN_MUST_USE_SECURITY_TOKEN')) {
        friendlyMsg = 'Salesforce requires a Security Token: Append your Salesforce security token to SF_PASSWORD.';
    } else if (rawMsg.includes('INVALID_OPERATION_WITH_EXPIRED_PASSWORD') || rawMsg.includes('PASSWORD_EXPIRED')) {
        friendlyMsg = 'Salesforce password has expired.';
    } else if (rawMsg.includes('NOT_FOUND') || rawMsg.includes('sObject type') || rawMsg.includes('INVALID_TYPE')) {
        friendlyMsg = 'Salesforce Contact object is not accessible with the current user permissions.';
    } else if (rawMsg.includes('ENOTFOUND') || rawMsg.includes('ECONNREFUSED') || rawMsg.includes('ETIMEDOUT') || rawMsg.includes('fetch failed')) {
        friendlyMsg = `Unable to connect to Salesforce login endpoint (${loginUrl || 'https://login.salesforce.com'}).`;
    }

    if (username) friendlyMsg = friendlyMsg.split(username).join('***');
    if (password) friendlyMsg = friendlyMsg.split(password).join('***');

    return friendlyMsg;
}

async function getContactPicklists() {
    const loginUrl = process.env.SF_LOGIN_URL || process.env.SALESFORCE_LOGIN_URL || 'https://login.salesforce.com';
    const username = process.env.SF_USERNAME || process.env.SALESFORCE_USERNAME || '';
    const password = process.env.SF_PASSWORD || process.env.SALESFORCE_PASSWORD || '';

    if (!username.trim() || !password.trim()) {
        return {
            success: false,
            error: 'Salesforce credentials are not configured.',
            fields: {}
        };
    }

    try {
        const conn = new jsforce.Connection({
            loginUrl: loginUrl.trim(),
            version: '61.0'
        });

        await conn.login(username.trim(), password.trim());

        // 1. Describe Contact Object
        const contactMeta = await conn.sobject('Contact').describe();

        const fieldResults = {};

        // 1. Deal Category: Deal_Category__c
        const dealCatField = (contactMeta.fields || []).find(
            f => f.name.toLowerCase() === 'deal_category__c'
        );

        if (dealCatField && dealCatField.picklistValues && dealCatField.picklistValues.length > 0) {
            fieldResults['Deal_Category__c'] = {
                key: 'deal_category',
                name: dealCatField.name,
                label: dealCatField.label || 'Deal Category',
                type: dealCatField.type,
                controllerName: dealCatField.controllerName || null,
                values: dealCatField.picklistValues
                    .filter(pv => pv.active !== false)
                    .map(pv => ({
                        label: pv.label || pv.value,
                        value: pv.value,
                        defaultValue: Boolean(pv.defaultValue),
                        validFor: pv.validFor || null
                    }))
            };
        } else {
            fieldResults['Deal_Category__c'] = {
                key: 'deal_category',
                name: 'Deal_Category__c',
                label: 'Deal Category',
                type: 'multipicklist',
                controllerName: null,
                values: []
            };
        }

        // 2. Region: Region_Acc__c
        const regionAccField = (contactMeta.fields || []).find(
            f => f.name.toLowerCase() === 'region_acc__c'
        );

        let regionValues = [];

        // Check if Contact.Region_Acc__c has picklistValues directly
        if (regionAccField && regionAccField.picklistValues && regionAccField.picklistValues.length > 0) {
            regionValues = regionAccField.picklistValues
                .filter(pv => pv.active !== false)
                .map(pv => ({
                    label: pv.label || pv.value,
                    value: pv.value,
                    defaultValue: Boolean(pv.defaultValue),
                    validFor: pv.validFor || null
                }));
        } else {
            // Region_Acc__c is a formula (Account.Region__c) or text field.
            // Retrieve configured region options from Account.Region__c or distinct values from Salesforce
            try {
                const accRes = await conn.query("SELECT Region__c FROM Account WHERE Region__c != null GROUP BY Region__c ORDER BY Region__c ASC");
                const distinct = (accRes.records || []).map(r => r.Region__c).filter(Boolean);
                if (distinct.length > 0) {
                    regionValues = distinct.map(rVal => ({
                        label: rVal,
                        value: rVal,
                        defaultValue: false,
                        validFor: null
                    }));
                }
            } catch (queryErr) {
                // If query fails, fallback to Lead.Region__c picklist
                try {
                    const leadMeta = await conn.sobject('Lead').describe();
                    const leadReg = (leadMeta.fields || []).find(f => f.name.toLowerCase() === 'region__c');
                    if (leadReg && leadReg.picklistValues) {
                        regionValues = leadReg.picklistValues
                            .filter(pv => pv.active !== false)
                            .map(pv => ({
                                label: pv.label || pv.value,
                                value: pv.value,
                                defaultValue: Boolean(pv.defaultValue),
                                validFor: pv.validFor || null
                            }));
                    }
                } catch (leadErr) {
                    // Fallback to standard 10 Salesforce regions
                    const standardRegions = ['APAC', 'Canada', 'CEE', 'Eurasia', 'LATAM', 'MEA', 'Sub-Saharan Africa', 'USA', 'West Europe', 'Other'];
                    regionValues = standardRegions.map(r => ({ label: r, value: r, defaultValue: false, validFor: null }));
                }
            }
        }

        const customRegion = 'Far East Asia + Southeast Asia Countries + Latin America';
        if (!regionValues.some(v => v.value.toLowerCase() === customRegion.toLowerCase())) {
            regionValues.push({
                label: customRegion,
                value: customRegion,
                defaultValue: false,
                validFor: null
            });
        }
        regionValues.sort((a, b) => a.label.localeCompare(b.label));

        fieldResults['Region_Acc__c'] = {
            key: 'region',
            name: regionAccField ? regionAccField.name : 'Region_Acc__c',
            label: regionAccField ? regionAccField.label : 'Region',
            type: regionAccField ? regionAccField.type : 'string',
            controllerName: regionAccField ? regionAccField.controllerName : null,
            values: regionValues
        };

        // 3. Country: MailingCountry
        const countryField = (contactMeta.fields || []).find(
            f => f.name.toLowerCase() === 'mailingcountry'
        );
        const countryCodeField = (contactMeta.fields || []).find(
            f => f.name.toLowerCase() === 'mailingcountrycode'
        );

        let countryValues = [];

        // Check if State and Country/Territory Picklists are enabled (MailingCountryCode or MailingCountry picklist)
        if (countryCodeField && countryCodeField.picklistValues && countryCodeField.picklistValues.length > 0) {
            // State & Country Picklists enabled in Salesforce!
            countryValues = countryCodeField.picklistValues
                .filter(pv => pv.active !== false)
                .map(pv => ({
                    label: pv.label || pv.value,
                    value: pv.label || pv.value, // User-friendly label as value for MailingCountry matching
                    code: pv.value,               // ISO country code (e.g. "US", "GB")
                    defaultValue: Boolean(pv.defaultValue),
                    validFor: pv.validFor || null
                }));
        } else if (countryField && countryField.picklistValues && countryField.picklistValues.length > 0) {
            countryValues = countryField.picklistValues
                .filter(pv => pv.active !== false)
                .map(pv => ({
                    label: pv.label || pv.value,
                    value: pv.value,
                    code: pv.value,
                    defaultValue: Boolean(pv.defaultValue),
                    validFor: pv.validFor || null
                }));
        } else {
            // Text field fallback: query distinct MailingCountry values from Salesforce Contact
            try {
                const ctyRes = await conn.query("SELECT MailingCountry FROM Contact WHERE MailingCountry != null GROUP BY MailingCountry ORDER BY MailingCountry ASC");
                const distinctCountries = (ctyRes.records || []).map(r => r.MailingCountry).filter(Boolean);
                countryValues = distinctCountries.map(c => ({
                    label: c,
                    value: c,
                    code: c,
                    defaultValue: false,
                    validFor: null
                }));
            } catch (ctyErr) {
                // Lead.Country_Custom__c fallback
                try {
                    const leadMeta = await conn.sobject('Lead').describe();
                    const leadCty = (leadMeta.fields || []).find(f => f.name.toLowerCase() === 'country_custom__c');
                    if (leadCty && leadCty.picklistValues) {
                        countryValues = leadCty.picklistValues
                            .filter(pv => pv.active !== false)
                            .map(pv => ({
                                label: pv.label || pv.value,
                                value: pv.value,
                                code: pv.value,
                                defaultValue: Boolean(pv.defaultValue),
                                validFor: pv.validFor || null
                            }));
                    }
                } catch (fallbackErr) {
                    countryValues = [];
                }
            }
        }

        fieldResults['MailingCountry'] = {
            key: 'country',
            name: countryField ? countryField.name : 'MailingCountry',
            label: countryField ? countryField.label : 'Country',
            type: countryField ? countryField.type : 'string',
            controllerName: countryField ? countryField.controllerName : null,
            stateCountryPicklistsEnabled: Boolean(countryCodeField && countryCodeField.picklistValues && countryCodeField.picklistValues.length > 0),
            values: countryValues
        };

        const recordTypes = (contactMeta.recordTypeInfos || []).map(rt => ({
            recordTypeId: rt.recordTypeId,
            name: rt.name,
            developerName: rt.developerName,
            available: rt.available,
            defaultRecordTypeMapping: rt.defaultRecordTypeMapping,
            master: rt.master
        }));

        return {
            success: true,
            object: 'Contact',
            cachedAt: new Date().toISOString(),
            fields: fieldResults,
            recordTypes
        };
    } catch (err) {
        return {
            success: false,
            error: sanitizeErrorMessage(err, loginUrl, username, password),
            fields: {}
        };
    }
}

async function main() {
    const result = await getContactPicklists();
    console.log(JSON.stringify(result, null, 2));
}

main().catch(err => {
    console.error(JSON.stringify({
        success: false,
        error: String(err),
        fields: {}
    }));
    process.exit(1);
});

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('sending_domains') && Schema::hasTable('servers')) {
            $server = DB::table('servers')->where('name', 'like', '%Pabbly%')->first()
                ?: DB::table('servers')->first();

            if ($server) {
                $serverId = $server->id;

                // Unset previous defaults
                DB::table('sending_domains')->update(['is_default' => 0]);

                $existing = DB::table('sending_domains')->where('domain_name', 'mailer-b2bexportsllc.com')->first();
                if ($existing) {
                    DB::table('sending_domains')->where('id', $existing->id)->update([
                        'status' => 'enabled',
                        'spf_status' => 'verified',
                        'dkim_status' => 'verified',
                        'dmarc_status' => 'verified',
                        'is_default' => 1,
                        'rate_limit_per_hour' => 10000,
                        'server_id' => $serverId,
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('sending_domains')->insert([
                        'domain_name' => 'mailer-b2bexportsllc.com',
                        'status' => 'enabled',
                        'spf_status' => 'verified',
                        'dkim_status' => 'verified',
                        'dmarc_status' => 'verified',
                        'server_id' => $serverId,
                        'is_default' => 1,
                        'rate_limit_per_hour' => 10000,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sending_domains')) {
            DB::table('sending_domains')->where('domain_name', 'proitbuyer.com')->update(['is_default' => 1]);
            DB::table('sending_domains')->where('domain_name', 'mailer-b2bexportsllc.com')->update(['is_default' => 0]);
        }
    }
};

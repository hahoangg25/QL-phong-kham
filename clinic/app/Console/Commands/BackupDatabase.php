<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/** HT-10: sao lưu cơ sở dữ liệu định kỳ bằng mysqldump, giữ N bản gần nhất. */
class BackupDatabase extends Command
{
    protected $signature = 'clinic:backup';

    protected $description = 'Sao lưu cơ sở dữ liệu MySQL';

    public function handle(): int
    {
        $db = config('database.connections.'.config('database.default'));

        if (($db['driver'] ?? '') !== 'mysql' && ($db['driver'] ?? '') !== 'mariadb') {
            $this->warn('Chỉ hỗ trợ sao lưu MySQL/MariaDB.');

            return self::SUCCESS;
        }

        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        $file = $dir.'/backup-'.now()->format('Ymd-His').'.sql';

        $args = [
            config('clinic.backup.mysqldump'),
            '--host='.$db['host'], '--port='.$db['port'], '--user='.$db['username'],
            '--single-transaction', '--routines', '--result-file='.$file, $db['database'],
        ];
        if ($db['password']) {
            $args[] = '--password='.$db['password'];
        }

        $process = new Process($args);
        $process->setTimeout(600)->run();

        if (! $process->isSuccessful()) {
            $this->error('Sao lưu thất bại: '.$process->getErrorOutput());

            return self::FAILURE;
        }

        // Giữ lại N bản mới nhất
        collect(File::files($dir))->sortByDesc(fn ($f) => $f->getMTime())
            ->slice(config('clinic.backup.keep'))
            ->each(fn ($f) => File::delete($f->getPathname()));

        $this->info('Đã sao lưu: '.$file);

        return self::SUCCESS;
    }
}

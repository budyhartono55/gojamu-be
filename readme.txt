masuk ke container php
- docker exec -it 83d3aa671362 sh 

masuk ke folder project (smartcity) didalam container
- cd portal-smartcity-ksb
Buat file .env dicopy dari .env.example 

Perintah sesudah clone project
1. composer insall
2. php artisan key:generate
3. php artisan db:seed --class=UserSeeder

Sebelum deploving code
1. Pastikan sebelum buat branch ,posisi branch harus ada di dev
2. Untuk membuat branch baru gunakan perintah git checkout -b namamodul(sesuaikan modul)
3. Setelah berhasil buat branch,mulailah bekerja di branch tersebut
4. Setelah hasil kerja sudah selesai,sebelum di push pastikan pull code terbaru dari server dengan perintah gpl origin dev agar tidak terjadi conflict
5. Setelah di push buat merge request di gitlab dengan tujuan branch ke dev tes coba prod


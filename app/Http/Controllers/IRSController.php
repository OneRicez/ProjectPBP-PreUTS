<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DetailIRS;
use App\Models\IRS;
use App\Models\Jadwal;
use App\Models\Mahasiswa;
use App\Models\MataKuliah;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class IRSController extends Controller
{
    // Display IRS page for a specific Mahasiswa
    public function index(Request $request)
    {
        // Get mahasiswa
        $mahasiswa = Auth::user()->mahasiswa;
        $nim = $mahasiswa->nim;
    
        // Menampilkan matkul sesuai semester mahasiswa
        $semesterMHS = Mahasiswa::where('nim', $nim)->value('semester');
        $irs = IRS::where('nim_mahasiswa', $nim)->where('semester',$semesterMHS)->first();

        // Ambil status mahasiswa
        $status = Mahasiswa::where('nim', $nim)->value('status');

        // check status setujui
        $statusIRS = $irs->status;
        // Jika sudah disetujui irs tidak bisa diakses
        if ($statusIRS === 'sudah_disetujui') {
            return redirect()->back()->with('error', 'IRS Sudah Disetujui');
        }

        // Jika belum melakukan registrasi dan status belum aktif
        if ($irs === null && $status != 'aktif') {
            return redirect()->back()->with('error', 'Registrasi Terlebih dahulu');
        }

        // prodi mhs
        $prodi = $mahasiswa->kode_prodi;

        // Get all Mata Kuliah for the semester
        $mataKuliah = MataKuliah::where('semester', $semesterMHS)
                    ->where('kode_prodi', $prodi)
                    ->get();
    
        // Get all Jadwal based on Mata Kuliah kode_mk
        // $jadwals = Jadwal::whereIn('kode_mk', $mataKuliah->pluck('kode_mk'))->get();

        $jadwals = Jadwal::whereIn('kode_mk', $mataKuliah->pluck('kode_mk'))
                    ->where('status', 'disetujui')
                    ->get();
    
        // Menampilkan jadwal tambahan
        $isGanjil = $semesterMHS % 2 !== 0;

        $mkTambahan = MataKuliah::where(function ($query) use ($isGanjil, $semesterMHS) {
                    $query->where('semester', '=', 0) // Semester 0 tetap dimasukkan
                        ->orWhere(function ($query) use ($isGanjil, $semesterMHS) {
                        $query->whereRaw('semester % 2 = ?', [$isGanjil ? 1 : 0]) // Ganjil/Genap
                            ->where('semester', '!=', $semesterMHS); // Tidak sama dengan semesterMHS
                        });
                    })->get();
    
        // Ambil mata kuliah yang sudah dipilih dari cookie
        $selectedMKs = collect(json_decode(Cookie::get("selectedMKs_user_{$nim}", '[]'), true));

        // Tambahkan jadwal berdasarkan mata kuliah yang dipilih
        // $selectedJadwals = Jadwal::whereIn('kode_mk', $selectedMKs->pluck('kode_mk'))->get();

        // Tambahkan jadwal berdasarkan mata kuliah yang dipilih
        $selectedJadwals = Jadwal::whereIn('kode_mk', $selectedMKs->pluck('kode_mk'))
                            ->where('status', 'disetujui')
                            ->get();

        // Gabungkan dengan jadwal yang ada
        $jadwals = $jadwals->merge($selectedJadwals);

        // Get all the detail IRS entries for this IRS
        $detailIrs = DetailIrs::where('id_irs', $irs->id_irs)->get();
    
        // Mengambil IRS terbaru
        $latestIrs = $mahasiswa->irs()->latest()->first();
    
        // Calculate total SKS
        $totalSKS = $detailIrs->sum(function ($detail) {
            return $detail->jadwal->mataKuliah->sks ?? 0;
        });
    
        return view('mahasiswa.irs_mhs', compact('irs', 'jadwals', 'selectedMKs', 'mataKuliah', 'mkTambahan', 'detailIrs', 'latestIrs', 'totalSKS'));
    }

    // Add jadwal to detail IRS
    public function add(Request $request)
    {
        $request->validate([
            'id_irs' => 'required|exists:irs,id_irs',
            'id_jadwal' => 'required|exists:jadwal,id_jadwal',
        ]);
    
        $id_jadwal = $request->id_jadwal;
        $id_irs = $request->id_irs;

        $irs = IRS::where('id_irs', $id_irs)->first();

        if ($irs->status === 'belum_irs') {
            $irs->status = 'belum_disetujui';
            $irs->save();
        }

        // Mendapatkan kode_mk dari tabel jadwal
        $kode_mk = Jadwal::where('id_jadwal', $id_jadwal)->value('kode_mk');

        // Mendapatkan semester dari kode_mk
        $semester = MataKuliah::where('kode_mk', $kode_mk)->value('semester');

        // Hitung Mahasiswa yang wajib
        $totalMHSWajib = Mahasiswa::where('semester', $semester)->count();

        // Mendapatkan kode_tahun
        $getTahun = IRS::where('id_irs', $id_irs)->value('kode_tahun');

        // Hitung Kelas pada MK
        $totalKelasMK = Jadwal::where('kode_mk', $kode_mk)->
                                where('kode_tahun', $getTahun)
                                ->count();

        // Ambil kode_kelas dan kuota langsung tanpa grup
        $kuotaPerKelas = Jadwal::where('kode_mk', $kode_mk)
                        ->where('kode_tahun', $getTahun)
                        ->select('kode_kelas', 'kuota')
                        ->orderBy('kuota', 'DESC')
                        ->get();

        // Hitung total kuota
        $totalKuota = $kuotaPerKelas->sum('kuota');

        // Tambahkan persentase ke setiap kelas
        $kuotaPerKelasWithPercentage = $kuotaPerKelas->map(function ($kelas) use ($totalKuota) {
            $kelas->percentage = ($totalKuota > 0) 
                ? round(($kelas->kuota / $totalKuota) * 100, 2) // Hitung persentase, bulatkan 2 desimal
                : 0; // Jika totalKuota adalah 0, persentase 0
            return $kelas;
        });

        // Tambahkan kuotaSisa ke setiap kelas berdasarkan persentase dan kuota asli
        $kuotaPerKelasWithKuotaSisa = $kuotaPerKelasWithPercentage->map(function ($kelas) {
            $kelas->kuotaSisa = (int) (($kelas->percentage / 100) * $kelas->kuota); // Hitung kuota sisa sebagai integer
            return $kelas;
        });

        $jadwal = Jadwal::where('id_jadwal', $id_jadwal)->first();

        $semesterAktor = IRS::where('id_irs', $id_irs)->value('semester');

        if ($semesterAktor < $semester) {
            $kuotaTerambil = DetailIRS::join('irs', 'detail_irs.id_irs', '=', 'irs.id_irs')
                                ->join('jadwal', 'detail_irs.id_jadwal', '=', 'jadwal.id_jadwal')
                                ->join('mata_kuliah', 'jadwal.kode_mk', '=', 'mata_kuliah.kode_mk')
                                ->where('detail_irs.id_jadwal', $id_jadwal)
                                ->whereColumn('irs.semester', '<', 'mata_kuliah.semester')
                                ->count();
            
            $kodeKelas = $jadwal->kode_kelas;
            
            
            foreach ($kuotaPerKelasWithKuotaSisa as $kelas) {
                // dd($kelas->kuotaSisa);
                if ($kelas->kode_kelas === $kodeKelas && $kuotaTerambil + 1 > $kelas->kuotaSisa){
                    return redirect()->back()->with('error', 'Jatah Tambahan kelas ini sudah penuh!');
                }
                // dd($kelas->kode_kelas);
            }  
        }
        
        // Handle kuota pengambilan kelas
        // Hitung jumlah id_jadwal di DetailIRS
        $jumlahPengguna = DetailIRS::where('id_jadwal', $id_jadwal)->count();

        // Ambil kuota dari tabel jadwal
        $kuota = Jadwal::where('id_jadwal', $id_jadwal)->value('kuota');

        // Periksa apakah kuota masih tersedia
        if ($jumlahPengguna >= $kuota) {
            return redirect()->back()->with('error', 'Jadwal sudah penuh!');
        }

        // Dapatkan informasi jadwal yang baru akan ditambahkan
        $newJadwal = Jadwal::findOrFail($id_jadwal);
        
        // Ambil jadwal yang sudah ada di IRS dengan relasi ke tabel jadwal
        $existingJadwals = DetailIrs::where('id_irs', $id_irs)
            ->with('jadwal')
            ->get();
    
        // Cek tabrakan jadwal
        $jadwalBentrok = $existingJadwals->first(function ($detailIrs) use ($newJadwal) {
            $existingJadwal = $detailIrs->jadwal;
            
            // Periksa apakah hari sama
            if ($existingJadwal->hari === $newJadwal->hari) {
                // Kondisi tabrakan yang lebih detail
                $isOverlapping = 
                    // Jadwal baru dimulai sebelum jadwal existing selesai dan berakhir setelah jadwal existing mulai
                    (($newJadwal->jam_mulai < $existingJadwal->jam_selesai) && 
                    ($newJadwal->jam_selesai > $existingJadwal->jam_mulai));
                
                return $isOverlapping;
            }
            
            return false;
        });
    
        // Cek apakah mata kuliah sudah diambil
        $existsMK = DetailIrs::where('id_irs', $id_irs)
            ->whereHas('jadwal', function ($query) use ($newJadwal) {
                $query->where('kode_mk', $newJadwal->kode_mk)
                    ->where('id_jadwal', '!=', $newJadwal->id_jadwal);
            })
            ->exists();
    
        if ($existsMK) {
            return redirect()->back()->with('error', 'Mata kuliah sudah diambil di jadwal lain!');
        }
    
        // Cek jadwal yang sama persis sudah ada
        $existsJadwal = DetailIrs::where('id_irs', $id_irs)
            ->where('id_jadwal', $id_jadwal)
            ->exists();
    
        if ($existsJadwal) {
            return redirect()->back()->with('error', 'Jadwal sudah ada!');
        }
    
        // Jika ada jadwal bentrok, kembalikan error
        if ($jadwalBentrok) {
            $existingMatkul = MataKuliah::where('kode_mk', $jadwalBentrok->jadwal->kode_mk)->first();
            
            return redirect()->back()->with('error', 
                "Jadwal bentrok dengan mata kuliah {$existingMatkul->nama_mk} pada hari {$jadwalBentrok->jadwal->hari}! "
            );
        }        
        
        // Sesuaikan sks mahasiswa
        // Ambil IPK terkini mahasiswa
        $getNIM = IRS::where('id_irs', $id_irs)->value('nim_mahasiswa');
        $getIPK = Mahasiswa::where('nim', $getNIM)->value('ipk');

        $getSKS = MataKuliah::where('kode_mk', $kode_mk)->value('sks');

        if ($getIPK < 2.00 && $semesterAktor != 1) {
            $jatahSKS = 18;
        } elseif ($getIPK >= 2.00 && $getIPK <= 2.49) {
            $jatahSKS = 20;
        } elseif ($getIPK >= 2.50 && $getIPK <= 2.99) {
            $jatahSKS = 22;
        } elseif ($getIPK >= 3.00 || $semesterAktor == 1) {
            $jatahSKS = 24;
        }

        // ambil total sks yang sudah ada dalam detail IRS
        $sksTerambil = DetailIrs::where('id_irs', $id_irs)->pluck('id_jadwal');

        // Ambil kode_mk dan sks dari tabel Jadwal dan MataKuliah
        $totalSKS = Jadwal::whereIn('id_jadwal', $sksTerambil)
            ->join('mata_kuliah', 'jadwal.kode_mk', '=', 'mata_kuliah.kode_mk')
            ->sum('mata_kuliah.sks');

        if (($totalSKS + $getSKS) > $jatahSKS) {
            return redirect()->back()->with('error', 'SKS tidak mencukupi');
        }
    
        // Tambahkan jadwal ke detail_irs
        DetailIrs::create([
            'id_irs' => $id_irs,
            'id_jadwal' => $id_jadwal,
        ]);
    
        return redirect()->route('mahasiswa.irs_mhs')->with('success', 'Jadwal berhasil ditambahkan!');
    }

    public function delete(Request $request)
    {   
        // Validate the incoming request
        $request->validate([
            'id_irs' => 'required|exists:detail_irs,id_irs',
            'id_jadwal' => 'required|exists:detail_irs,id_jadwal',
        ]);
    
        // Find the record based on id_irs and id_jadwal
        $detailIrs = DetailIrs::where('id_irs', $request->id_irs)
            ->where('id_jadwal', $request->id_jadwal)
            ->first();

        // Delete the record
        $detailIrs->delete();
    
        // Redirect back with a success message
        return redirect()->route('mahasiswa.irs_mhs')->with('success', 'Jadwal berhasil dihapus!');
    }

    public function updateMK(Request $request)
    {
        // Validasi input
        $validated = $request->validate([
            'action' => 'required|in:add,remove',
            'selectedMK' => 'required|array',
            'selectedMK.*.id' => 'exists:mata_kuliah,kode_mk',
        ]);

        // Dapatkan ID pengguna
        $mahasiswa = Auth::user()->mahasiswa;
        $nim = $mahasiswa->nim;

        // Nama cookie unik per pengguna
        $cookieName = "selectedMKs_user_{$nim}";

        // Ambil data mata kuliah yang dipilih
        $selectedMKIds = collect($validated['selectedMK'])->pluck('id')->toArray();
        $selectedMKs = MataKuliah::whereIn('kode_mk', $selectedMKIds)->get();

        // Ambil data mata kuliah yang disimpan di cookie
        $existingMKs = json_decode(Cookie::get($cookieName, '[]'), true);

        if ($validated['action'] === 'add') {
            // Gabungkan data baru dengan data lama
            $mergedMKs = collect($existingMKs)->merge($selectedMKs)->unique('kode_mk')->values();
            $message = 'Mata kuliah berhasil ditambahkan!';
        } else {
            // Hapus mata kuliah yang dipilih
            $mergedMKs = collect($existingMKs)->reject(function ($mataKuliah) use ($selectedMKIds) {
                return in_array($mataKuliah['kode_mk'], $selectedMKIds);
            });
            $message = 'Mata kuliah berhasil dihapus!';
        }

        // Hitung durasi dalam detik untuk 30 hari
        $oneMonth = 30 * 24 * 60 * 60;

        // Simpan cookie selama satu bulan
        Cookie::queue($cookieName, $mergedMKs->toJson(), $oneMonth);

        // Redirect dengan flash message
        return redirect()->route('mahasiswa.irs_mhs')->with('success', $message);
    }


}

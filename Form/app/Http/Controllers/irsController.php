<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\irs; // Model IRS
use App\Models\Mata_Kuliah; // Model Mata Kuliah
use App\Models\Kelas; // Model Kelas
use App\Models\Mahasiswa; // Model Mahasiswa
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class irsController extends Controller
{
    public function simpanirs(Request $request)
    {
    
        // Ambil NIM mahasiswa berdasarkan user yang sedang login
        $userId = Auth::user()->id;
        $mahasiswa = Mahasiswa::where('user_id', $userId)->first();
    
        if (!$mahasiswa) {
            return response()->json(['error' => 'Mahasiswa tidak ditemukan'], 404);
        }
    
        $mahasiswaNim = $mahasiswa->nim;
        // Ambil SKS Kumulatif mahasiswa
    $sksKumulatif = $mahasiswa->SKS_Kumulatif;

    // Hitung total SKS dari mata kuliah yang ditambahkan
    $totalSksToAdd = 0;
        foreach ($request->collect('irs') as $course) {
         
            // Pastikan semester ada
            if (!isset($course['semester'])) {
                return response()->json(['error' => 'Semester is required for each course'], 400);
            }
            
    
            // Cari mata kuliah berdasarkan kode MK
            $mataKuliah = Mata_Kuliah::where('kode_mk', $course['mata_kuliah_kode_mk'])->first();
            if (!$mataKuliah) {
                return response()->json(['error' => 'Mata kuliah dengan kode ' . $course['mata_kuliah_kode_mk'] . ' tidak ditemukan'], 404);
            }

            // Cari kelas berdasarkan kode mata kuliah dan semester
            $kelas = Kelas::where('mata_kuliah_kode_mk', $course['mata_kuliah_kode_mk'])
                          ->where('tahun_akademik', $course['tahun_akademik'] ?? $course['tahun_Akademik'])
                          ->first();

            if (!$kelas) {
                return response()->json(['error' => 'Kelas untuk mata kuliah ' . $course['mata_kuliah_kode_mk'] . ' tidak ditemukan'], 404);
            }
            
            $existingIrs = Irs::where('mahasiswa_nim', $mahasiswaNim)
            ->whereHas('kelas.mataKuliah', function ($query) use ($mataKuliah) {
                $query->where('mata_kuliah.kode_mk', $mataKuliah->kode_mk);
            })
            ->exists();

            // Jika sudah ada, abaikan dan lanjutkan ke course berikutnya
            if ($existingIrs) {
            continue; // Tidak menambah jika sudah ada
            }
            // Simpan atau update IRS
            Irs::create(
                [
                    'mahasiswa_nim' => $mahasiswaNim,
                    'semester' => $course['semester'],
                    'tahun_akademik' => $course['tahun_akademik'] ?? $course['tahun_Akademik'],
                    'total_sks' => $course['total_sks'],
                    'ruang_kuliah_kode_ruang' => $course['ruang_kuliah_kode_ruang'],
                    'kelas_id' => $course['kelas_id'], // Menghubungkan dengan ID kelas
                    'is_verified' => $course['is_verified'] ?? 0, // Nilai default jika tidak ada
                    'diajukan' => $course['diajukan'] ?? 0, // Nilai default jika tidak ada
                ]
            );
        }
    
        return response()->json(['message' => 'Simpan IRS berhasil', 'data' => $request->input('irs')], 200);
    }


    public function destroy($kode_mk)
{
    // Cari IRS berdasarkan kode mata kuliah
    $irs = Irs::whereHas('kelas.mataKuliah', function ($query) use ($kode_mk) {
        $query->where('kode_mk', $kode_mk);
    })->first();

    if ($irs) {
        $irs->delete(); // Hapus data IRS
        return response()->json(['message' => 'Mata kuliah berhasil dihapus.'], 200);
    }

    // return response()->json(['message' => 'Mata kuliah tidak ditemukan.'], 404);
}

    public function getIrsData($nim)
    {
        error_log("sini");
        $irsData = irs::where('mahasiswa_nim', $nim)->get();
        return response()->json($irsData);

    }



    public function showIRS()
    {
        // Ambil mahasiswa yang sedang login
        $userId = Auth::user()->id;
        $mahasiswa = Mahasiswa::where('user_id', $userId)->first();
    
        if (!$mahasiswa) {
            return redirect()->back()->with('error', 'Mahasiswa tidak ditemukan.');
        }
    
        // Ambil semua IRS yang terkait mahasiswa tersebut
        $irs = irs::with('kelas.mataKuliah')
            ->where('mahasiswa_nim', $mahasiswa->nim)
            ->join('mata_kuliah', 'mata_kuliah.kode_mk', '=', 'irs.kode_mk')
            ->get();

        error_log($irs);
    
        // Hitung total SKS dari IRS
        $totalSKS = $irs->sum(fn($course) => $course->kelas->mataKuliah->sks ?? 0);
    
        // Kirim data ke view
        return view('irs.index', [
            'irs' => $irs,
            'totalSKS' => $totalSKS,
        ]);
    }
    

}
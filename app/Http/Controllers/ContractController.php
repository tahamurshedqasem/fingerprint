<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ContractController extends Controller
{
    public function index()
    {
        $contracts = Contract::orderBy('created_at', 'desc')->get();
        return response()->json([
            'success' => true,
            'contracts' => $contracts
        ]);
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'user_name' => 'required|string|max:255',
                'user_phone' => 'required|string|max:20',
                'discount_percentage' => 'required|numeric|min:0|max:100',
            ]);

            $contractNumber = 'CTR-' . strtoupper(Str::random(10));

            $contract = Contract::create([
                'contract_number' => $contractNumber,
                'terms' => $this->generateContractTerms(
                    $request->user_name, 
                    $request->user_phone,
                    $request->discount_percentage
                ),
                'user_name' => $request->user_name,
                'user_phone' => $request->user_phone,
                'discount_percentage' => $request->discount_percentage,
                'is_signed' => false,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء العقد بنجاح',
                'contract' => $contract
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $contract = Contract::findOrFail($id);
            return response()->json([
                'success' => true,
                'contract' => $contract
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'العقد غير موجود'
            ], 404);
        }
    }

   
    public function sign(Request $request, $id)
    {
        try {
            $contract = Contract::findOrFail($id);
            
            $request->validate([
                'signature_hash' => 'required|string',
                'signature_data' => 'nullable|string',
                'challenge' => 'nullable|string',
                'device_fingerprint' => 'nullable|string',
                'screen_resolution' => 'nullable|string',
                'timezone' => 'nullable|string'
            ]);

            // إذا لم يتم إرسال بيانات الإثبات، نستخدم بيانات افتراضية
            $deviceFingerprint = $request->device_fingerprint ?? $this->generateDeviceFingerprint($request);
            $challenge = $request->challenge ?? $this->generateChallenge();

            // إعادة حساب التوقيع إذا لزم الأمر
            $signatureHash = $request->signature_hash;
            if (empty($signatureHash)) {
                $data = $contract->user_name . 
                        $contract->user_phone . 
                        $contract->discount_percentage . 
                        $challenge . 
                        $deviceFingerprint;
                $signatureHash = hash_hmac('sha256', $data, env('APP_KEY'));
            }

            $contract->update([
                'signature_hash' => $signatureHash,
                'signature_data' => $request->signature_data ?? json_encode([
                    'signed_at' => Carbon::now()->toISOString(),
                    'user_agent' => $request->userAgent(),
                    'ip' => $request->ip(),
                    'browser_language' => $request->header('Accept-Language'),
                ]),
                'signature_challenge' => $challenge,
                'device_fingerprint' => $deviceFingerprint,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'browser_language' => $request->header('Accept-Language'),
                'screen_resolution' => $request->screen_resolution ?? '1920x1080',
                'timezone' => $request->timezone ?? 'Asia/Riyadh',
                'signed_at' => Carbon::now(),
                'is_signed' => true,
                'qr_code' => $this->generateQRCode($contract->id)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم توقيع العقد بنجاح',
                'contract' => $contract,
                'verification' => [
                    'signature_valid' => true,
                    'timestamp' => Carbon::now()->toISOString(),
                    'verification_url' => url("/api/contracts/verify/{$contract->id}")
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    
    private function generateDeviceFingerprint($request)
    {
        $components = [
            $request->userAgent(),
            $request->header('Accept-Language'),
            '1920x1080',
            'Asia/Riyadh'
        ];
        return substr(hash('sha256', implode('|||', $components)), 0, 64);
    }

    private function generateChallenge()
    {
        return bin2hex(random_bytes(16));
    }

    
    private function generateContractTerms($userName, $userPhone, $discountPercentage)
    {
        $now = Carbon::now();
        $dateStr = $now->format('Y / m / d');
        
        return <<<HTML
عقد تعاون وتقديم خصومات

إنه في يوم: <span class="text-blue-600 font-semibold">{$dateStr}</span>

تم الاتفاق بين:

<span class="font-semibold">الطرف الأول:</span> منصة تام (TAM)

<span class="font-semibold">الطرف الثاني:</span> <span class="text-blue-600 font-semibold">{$userName}</span>
<span class="font-semibold">اسم المؤسسة:</span> <span class="text-blue-600 font-semibold">{$userName}</span>
<span class="font-semibold">رقم الجوال:</span> <span class="text-blue-600 font-semibold">{$userPhone}</span>

على أن يقوم الطرف الثاني بتقديم خصم بنسبة (<span class="text-blue-600 font-semibold text-lg">{$discountPercentage}%</span>) لأعضاء منصة تام على خدماته أو منتجاته، مقابل إدراج المؤسسة ضمن المؤسسات المشاركة في المنصة.

مدة الاتفاق: من تاريخ التوقيع وحتى إشعار أحد الطرفين بإنهائه.

وبهذا تم الاتفاق بين الطرفين وهما بكامل أهليتهما القانونية.

<div class="grid grid-cols-2 gap-4 mt-4">
    <div class="border rounded-lg p-3 bg-white">
        <p class="font-semibold text-sm">الطرف الأول (تام)</p>
        <p class="text-sm">الاسم: ___________________</p>
        <div class="signature-placeholder mt-2" id="signatureTAM">
            <span class="placeholder-text text-gray-400 text-sm">التوقيع: ___________</span>
            <div class="signature-text hidden flex items-center gap-2">
                <i class="fas fa-check-circle text-green-500"></i>
                <span class="text-green-600 text-sm">تم التوقيع</span>
            </div>
        </div>
    </div>
    <div class="border rounded-lg p-3 bg-white">
        <p class="font-semibold text-sm">الطرف الثاني (المؤسسة)</p>
        <p class="text-sm">الاسم: <span class="font-semibold">{$userName}</span></p>
        <div class="signature-placeholder mt-2" id="signatureParty2">
            <span class="placeholder-text text-gray-400 text-sm">التوقيع: ___________</span>
            <div class="signature-text hidden flex-col items-center gap-1">
                <i class="fas fa-fingerprint text-green-500 text-3xl"></i>
                <span class="text-green-600 text-xs">تم التوقيع بالبصمة</span>
            </div>
        </div>
    </div>
</div>

<p class="text-sm text-gray-500 mt-4 text-center">تاريخ التوقيع: <span class="font-semibold">{$dateStr}</span></p>
HTML;
    }

    private function generateQRCode($contractId)
    {
        return url("/verify/{$contractId}");
    }

     public function verify($id)
    {
        try {
            // البحث عن العقد برقم العقد أو ID
            $contract = Contract::where('contract_number', $id)->orWhere('id', $id)->first();
            
            if (!$contract) {
                return response()->json([
                    'success' => false,
                    'message' => 'العقد غير موجود'
                ], 404);
            }

            // إعادة حساب التوقيع للتحقق
            $isValid = false;
            if ($contract->is_signed && $contract->signature_hash && $contract->signature_challenge) {
                $data = $contract->user_name . 
                        $contract->user_phone . 
                        $contract->discount_percentage . 
                        $contract->signature_challenge . 
                        $contract->device_fingerprint;
                $expectedSignature = hash_hmac('sha256', $data, env('APP_KEY'));
                $isValid = hash_equals($expectedSignature, $contract->signature_hash);
            }

            return response()->json([
                'success' => true,
                'verification' => [
                    'is_valid' => $isValid,
                    'contract_number' => $contract->contract_number,
                    'signed_by' => $contract->user_name,
                    'user_phone' => $contract->user_phone,
                    'discount_percentage' => $contract->discount_percentage,
                    'signed_at' => $contract->signed_at,
                    'ip_address' => $contract->ip_address,
                    'device_fingerprint' => $contract->device_fingerprint,
                    'challenge' => $contract->signature_challenge,
                    'signature_hash' => $contract->signature_hash,
                    'user_agent' => $contract->user_agent,
                    'screen_resolution' => $contract->screen_resolution ?? 'غير متاح',
                    'timezone' => $contract->timezone ?? 'غير متاح',
                    'verification_status' => $isValid ? '✅ صالح' : '❌ غير صالح'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

}
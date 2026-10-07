import { Calculator, CheckCircle2, ChevronRight, HelpCircle, Info, Play, Sparkles } from 'lucide-react';
import React, { useEffect, useState } from 'react';

export default function FormulaBuilder({
    formula,
    onChange,
    dayType = 'working_day',
    readOnly = false,
}) {
    // Determine mode based on formula structure or dayType
    const isHolidayType = dayType === 'holiday' || dayType === 'national_holiday' || dayType === 'joint_leave';

    // Extract multiplier values from formula AST if present, or set sensible defaults
    const extractMultiplier = (ast, varName, fallback) => {
        if (!ast) return fallback;
        const findVar = (node) => {
            if (!node) return null;
            if (node.variable === varName) return node.default ?? fallback;
            if (Array.isArray(node.operands)) {
                for (const op of node.operands) {
                    const found = findVar(op);
                    if (found !== null) return found;
                }
            }
            return null;
        };
        const val = findVar(ast);
        return val !== null ? Number(val) : fallback;
    };

    const [firstHourMult, setFirstHourMult] = useState(() =>
        extractMultiplier(formula, 'first_hour_multiplier', 1.5)
    );
    const [subsequentHourMult, setSubsequentHourMult] = useState(() =>
        extractMultiplier(formula, 'subsequent_hour_multiplier', 2.0)
    );
    const [holidayMult, setHolidayMult] = useState(() =>
        extractMultiplier(formula, 'holiday_overtime_multiplier', 2.0)
    );

    // Test calculation simulator state
    const [testRate, setTestRate] = useState(25000);
    const [testDays, setTestDays] = useState(3);
    const [testHours, setTestHours] = useState(8);
    const [testResult, setTestResult] = useState(null);
    const [isTesting, setIsTesting] = useState(false);

    // Update parent AST when multipliers change
    useEffect(() => {
        if (readOnly) return;

        if (isHolidayType) {
            const holidayAst = {
                name: 'holiday_pay',
                label: 'Total Lembur Hari Libur',
                type: 'multiply',
                break_threshold: 4.0,
                break_duration_per_day: 1.0,
                operands: [
                    { variable: 'total_overtime_hours' },
                    { variable: 'employee_overtime_rate' },
                    { variable: 'holiday_overtime_multiplier', default: Number(holidayMult) || 2.0 },
                ],
            };
            onChange(holidayAst);
        } else {
            const workingAst = {
                type: 'add',
                label: 'Total Upah Lembur',
                break_threshold: 3.0,
                break_duration_per_day: 0.5,
                operands: [
                    {
                        name: 'first_hours_pay',
                        label: 'Jam Pertama',
                        type: 'multiply',
                        operands: [
                            { variable: 'employee_overtime_rate' },
                            { variable: 'overtime_days' },
                            { variable: 'first_hour_multiplier', default: Number(firstHourMult) || 1.5 },
                        ],
                    },
                    {
                        name: 'subsequent_hours_pay',
                        label: 'Jam Berikutnya',
                        type: 'multiply',
                        operands: [
                            { variable: 'subsequent_overtime_hours' },
                            { variable: 'employee_overtime_rate' },
                            { variable: 'subsequent_hour_multiplier', default: Number(subsequentHourMult) || 2.0 },
                        ],
                    },
                ],
            };
            onChange(workingAst);
        }
    }, [firstHourMult, subsequentHourMult, holidayMult, isHolidayType, readOnly]);

    // Local client-side formula evaluation for instant preview & test verification
    const runTestSimulation = () => {
        setIsTesting(true);
        const rate = Number(testRate) || 0;
        const days = Number(testDays) || 0;
        const totalHours = Number(testHours) || 0;

        if (totalHours <= 0 || rate <= 0) {
            setTestResult({
                total: 0,
                steps: [
                    { label: 'Total Jam Lembur', detail: `${totalHours} jam (hasil = Rp 0)` },
                ],
            });
            setIsTesting(false);
            return;
        }

        const overtimeMinutes = Math.round(totalHours * 60);

        if (isHolidayType) {
            // Holiday: threshold >= 4.0 jam (>= 240 menit), potongan = days * 1.0 jam
            const thresholdMinutes = 240;
            const isDeducted = days > 0 && overtimeMinutes >= thresholdMinutes;
            const breakDeductionHours = isDeducted ? days * 1.0 : 0;
            const netHours = Math.max(totalHours - breakDeductionHours, 0);

            const mult = Number(holidayMult) || 2.0;
            const total = netHours * rate * mult;

            setTestResult({
                total,
                grossHours: totalHours,
                breakDeductionHours,
                netHours,
                isDeducted,
                steps: [
                    {
                        label: 'Potongan Istirahat Lembur (≥ 4 jam)',
                        detail: isDeducted
                            ? `Durasi (${totalHours} jam = ${overtimeMinutes} mnt) ≥ 240 mnt: Dipotong ${days} hari × 1.0 jam = ${breakDeductionHours} jam (Jam Bersih = ${netHours} jam)`
                            : `Durasi (${totalHours} jam = ${overtimeMinutes} mnt) < 240 mnt: Tidak dipotong (Jam Bersih = ${netHours} jam)`,
                    },
                    {
                        label: 'Lembur Hari Libur',
                        detail: `${netHours} jam bersih × Rp ${rate.toLocaleString('id-ID')} × ${mult} = Rp ${total.toLocaleString('id-ID')}`,
                    },
                ],
            });
        } else {
            // Working day: threshold >= 3.0 jam (>= 180 menit), potongan = days * 0.5 jam
            const thresholdMinutes = 180;
            const isDeducted = days > 0 && overtimeMinutes >= thresholdMinutes;
            const breakDeductionHours = isDeducted ? days * 0.5 : 0;
            const netHours = Math.max(totalHours - breakDeductionHours, 0);

            const fMult = Number(firstHourMult) || 1.5;
            const sMult = Number(subsequentHourMult) || 2.0;

            const firstHourUnits = Math.min(days * 1, netHours);
            const subsequentHours = Math.max(netHours - firstHourUnits, 0);

            const firstHourAmount = rate * firstHourUnits * fMult;
            const subsequentAmount = subsequentHours * rate * sMult;
            const total = firstHourAmount + subsequentAmount;

            setTestResult({
                total,
                grossHours: totalHours,
                breakDeductionHours,
                netHours,
                isDeducted,
                firstHourAmount,
                subsequentAmount,
                subsequentHours,
                firstHourUnits,
                steps: [
                    {
                        label: 'Potongan Istirahat Lembur (≥ 3 jam)',
                        detail: isDeducted
                            ? `Durasi (${totalHours} jam = ${overtimeMinutes} mnt) ≥ 180 mnt: Dipotong ${days} hari × 0.5 jam = ${breakDeductionHours} jam (Jam Bersih = ${netHours} jam)`
                            : `Durasi (${totalHours} jam = ${overtimeMinutes} mnt) < 180 mnt: Tidak dipotong (Jam Bersih = ${netHours} jam)`,
                    },
                    {
                        label: 'Jam Pertama',
                        detail: `Rp ${rate.toLocaleString('id-ID')} × ${firstHourUnits} jam × ${fMult} = Rp ${firstHourAmount.toLocaleString('id-ID')}`,
                    },
                    {
                        label: 'Jam Berikutnya',
                        detail: `(${netHours} jam bersih - ${firstHourUnits} jam pertama = ${subsequentHours} jam) × Rp ${rate.toLocaleString('id-ID')} × ${sMult} = Rp ${subsequentAmount.toLocaleString('id-ID')}`,
                    },
                ],
            });
        }
        setIsTesting(false);
    };

    // Run simulator automatically on mount and whenever parameters change
    useEffect(() => {
        runTestSimulation();
    }, [firstHourMult, subsequentHourMult, holidayMult, testRate, testDays, testHours, isHolidayType]);

    return (
        <div className="space-y-6">
            {/* Visual Formula Builder Block */}
            <div className="bg-slate-50 border border-slate-200 rounded-xl p-5 shadow-sm">
                <div className="flex items-center justify-between pb-3 mb-4 border-b border-slate-200">
                    <div className="flex items-center space-x-2">
                        <Sparkles className="w-5 h-5 text-indigo-600" />
                        <h4 className="font-semibold text-slate-800 text-base">Visual Formula Builder</h4>
                    </div>
                    <span className="text-xs px-2.5 py-1 font-medium bg-indigo-100 text-indigo-700 rounded-full">
                        {isHolidayType ? 'Rule Lembur Hari Libur' : 'Rule Lembur Hari Kerja'}
                    </span>
                </div>

                {isHolidayType ? (
                    /* Holiday Overtime Flow */
                    <div className="bg-white p-5 rounded-lg border border-slate-200 space-y-4">
                        <div className="text-sm font-medium text-slate-700">Alur Hitung Lembur Hari Libur:</div>
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="px-3 py-1.5 bg-sky-50 border border-sky-200 text-sky-800 font-semibold rounded-lg text-sm">
                                [ Total Jam Lembur ]
                            </span>
                            <span className="text-lg font-bold text-slate-400">×</span>
                            <span className="px-3 py-1.5 bg-emerald-50 border border-emerald-200 text-emerald-800 font-semibold rounded-lg text-sm">
                                [ Upah Lembur Karyawan ]
                            </span>
                            <span className="text-lg font-bold text-slate-400">×</span>
                            <div className="flex items-center space-x-1.5">
                                <span className="text-sm text-slate-500 font-medium">[ Multiplier:</span>
                                <input
                                    type="number"
                                    step="0.05"
                                    min="0"
                                    disabled={readOnly}
                                    value={holidayMult}
                                    onChange={(e) => setHolidayMult(e.target.value)}
                                    className="w-20 px-2 py-1 text-sm font-bold text-center text-indigo-700 border border-indigo-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                                />
                                <span className="text-sm text-slate-500 font-medium">]</span>
                            </div>
                        </div>
                    </div>
                ) : (
                    /* Working Day Overtime Flow */
                    <div className="space-y-4">
                        <div className="p-3 bg-indigo-50/70 border border-indigo-100 rounded-lg text-xs text-indigo-800 flex items-center justify-between">
                            <span>Alur Utama:</span>
                            <span className="font-bold text-indigo-900 bg-white px-2 py-0.5 rounded border border-indigo-200">
                                [ Jam Pertama ] + [ Jam Berikutnya ]
                            </span>
                        </div>

                        {/* Block 1: Jam Pertama */}
                        <div className="bg-white p-4 rounded-lg border border-slate-200 space-y-3">
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-bold text-slate-800">1. Cara Hitung Jam Pertama:</span>
                                <span className="text-xs text-slate-500 bg-slate-100 px-2 py-0.5 rounded">1 jam pertama per hari lembur</span>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="px-3 py-1.5 bg-emerald-50 border border-emerald-200 text-emerald-800 font-semibold rounded-lg text-sm">
                                    [ Upah Lembur Karyawan ]
                                </span>
                                <span className="text-lg font-bold text-slate-400">×</span>
                                <span className="px-3 py-1.5 bg-amber-50 border border-amber-200 text-amber-800 font-semibold rounded-lg text-sm">
                                    [ Jumlah Hari Lembur ]
                                </span>
                                <span className="text-lg font-bold text-slate-400">×</span>
                                <div className="flex items-center space-x-1.5">
                                    <span className="text-sm text-slate-500 font-medium">[ Multiplier:</span>
                                    <input
                                        type="number"
                                        step="0.05"
                                        min="0"
                                        disabled={readOnly}
                                        value={firstHourMult}
                                        onChange={(e) => setFirstHourMult(e.target.value)}
                                        className="w-20 px-2 py-1 text-sm font-bold text-center text-indigo-700 border border-indigo-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                    <span className="text-sm text-slate-500 font-medium">]</span>
                                </div>
                            </div>
                        </div>

                        {/* Block 2: Jam Berikutnya */}
                        <div className="bg-white p-4 rounded-lg border border-slate-200 space-y-3">
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-bold text-slate-800">2. Cara Hitung Jam Berikutnya:</span>
                                <span className="text-xs text-slate-500 bg-slate-100 px-2 py-0.5 rounded">
                                    Jam Berikutnya = Total Jam - (Hari × 1)
                                </span>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="px-3 py-1.5 bg-purple-50 border border-purple-200 text-purple-800 font-semibold rounded-lg text-sm">
                                    [ Jam Berikutnya ]
                                </span>
                                <span className="text-lg font-bold text-slate-400">×</span>
                                <span className="px-3 py-1.5 bg-emerald-50 border border-emerald-200 text-emerald-800 font-semibold rounded-lg text-sm">
                                    [ Upah Lembur Karyawan ]
                                </span>
                                <span className="text-lg font-bold text-slate-400">×</span>
                                <div className="flex items-center space-x-1.5">
                                    <span className="text-sm text-slate-500 font-medium">[ Multiplier:</span>
                                    <input
                                        type="number"
                                        step="0.05"
                                        min="0"
                                        disabled={readOnly}
                                        value={subsequentHourMult}
                                        onChange={(e) => setSubsequentHourMult(e.target.value)}
                                        className="w-20 px-2 py-1 text-sm font-bold text-center text-indigo-700 border border-indigo-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                    <span className="text-sm text-slate-500 font-medium">]</span>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* Live Formula Preview in Natural Human Language */}
            <div className="bg-gradient-to-r from-slate-900 to-indigo-950 text-white rounded-xl p-5 shadow-sm space-y-3">
                <div className="flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-indigo-300">
                    <span>Formula Preview (Bahasa Manusia)</span>
                    <span className="text-[11px] bg-indigo-500/30 px-2 py-0.5 rounded border border-indigo-400/30">
                        Evaluated Engine AST
                    </span>
                </div>
                {isHolidayType ? (
                    <div className="space-y-2 font-mono text-xs leading-relaxed text-indigo-100">
                        <div className="text-amber-300 font-sans font-semibold">
                            Ketentuan Potongan Istirahat: Potongan mulai berlaku pada lembur ≥ 4.0 jam (≥ 240 menit).
                        </div>
                        <div className="text-[11px] text-indigo-200">
                            Contoh: 3:59 (Tidak dipotong) | 4:00 (Dipotong) | 4:01 (Dipotong) | 5:00 (Dipotong)
                        </div>
                        <div className="text-white font-semibold pt-1 border-t border-indigo-800/60">
                            Total Lembur Hari Libur = Total Jam Lembur Bersih × Upah Lembur Karyawan × {holidayMult}
                        </div>
                    </div>
                ) : (
                    <div className="space-y-2 font-mono text-xs leading-relaxed text-indigo-100">
                        <div className="text-amber-300 font-sans font-semibold">
                            Ketentuan Potongan Istirahat: Potongan mulai berlaku pada lembur ≥ 3.0 jam (≥ 180 menit).
                        </div>
                        <div className="text-[11px] text-indigo-200">
                            Contoh: 2:59 (Tidak dipotong) | 3:00 (Dipotong) | 3:01 (Dipotong) | 4:00 (Dipotong)
                        </div>
                        <div className="text-white font-semibold pt-1 border-t border-indigo-800/60">
                            Total = Jam Pertama + Jam Berikutnya
                        </div>
                        <div className="text-xs text-indigo-200">
                            • Jam Pertama: Upah Lembur Karyawan × Jumlah Hari Lembur × {firstHourMult}
                        </div>
                        <div className="text-xs text-indigo-200">
                            • Jam Berikutnya: (Total Jam Lembur Bersih - Jumlah Hari Lembur) × Upah Lembur Karyawan × {subsequentHourMult}
                        </div>
                    </div>
                )}
            </div>

            {/* Test Formula Interactive Simulation Box */}
            <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-4">
                <div className="flex items-center justify-between border-b border-slate-200 pb-3">
                    <div className="flex items-center space-x-2">
                        <Calculator className="w-5 h-5 text-emerald-600" />
                        <h4 className="font-semibold text-slate-800 text-sm">Simulasi / Test Formula</h4>
                    </div>
                    <button
                        type="button"
                        onClick={runTestSimulation}
                        className="inline-flex items-center text-xs font-medium px-2.5 py-1 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 rounded-md transition"
                    >
                        <Play className="w-3.5 h-3.5 mr-1" />
                        Hitung Ulang
                    </button>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label className="block text-xs font-medium text-slate-600 mb-1">
                            Upah Lembur Karyawan (Rp/jam)
                        </label>
                        <input
                            type="number"
                            min="0"
                            step="1000"
                            value={testRate}
                            onChange={(e) => setTestRate(e.target.value)}
                            className="w-full text-sm rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                    {!isHolidayType && (
                        <div>
                            <label className="block text-xs font-medium text-slate-600 mb-1">
                                Jumlah Hari Lembur
                            </label>
                            <input
                                type="number"
                                min="0"
                                value={testDays}
                                onChange={(e) => setTestDays(e.target.value)}
                                className="w-full text-sm rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    )}
                    <div>
                        <label className="block text-xs font-medium text-slate-600 mb-1">
                            Total Jam Lembur
                        </label>
                        <input
                            type="number"
                            min="0"
                            step="0.5"
                            value={testHours}
                            onChange={(e) => setTestHours(e.target.value)}
                            className="w-full text-sm rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                </div>

                {/* Simulation Output Display */}
                {testResult && (
                    <div className="bg-emerald-50/60 border border-emerald-200 rounded-lg p-4 space-y-2">
                        <div className="text-xs font-semibold text-emerald-800 uppercase tracking-wide">
                            Hasil Perhitungan Simulasi:
                        </div>
                        <div className="space-y-1 text-xs text-slate-700">
                            {testResult.steps.map((step, idx) => (
                                <div key={idx} className="flex flex-col sm:flex-row sm:items-center justify-between border-b border-emerald-100 pb-1">
                                    <span className="font-semibold text-slate-800">{step.label}:</span>
                                    <span className="font-mono text-slate-600">{step.detail}</span>
                                </div>
                            ))}
                        </div>
                        <div className="pt-2 flex items-center justify-between font-bold text-sm text-emerald-900 border-t border-emerald-200">
                            <span>TOTAL UPAH LEMBUR:</span>
                            <span className="text-base text-emerald-700">
                                Rp {Number(testResult.total).toLocaleString('id-ID')}
                            </span>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

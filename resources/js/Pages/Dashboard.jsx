import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';

export default function Dashboard() {
    const [progress, setProgress] = useState(0);
    const [status, setStatus] = useState('idle'); // idle, processing, completed
    const [codePattern, setCodePattern] = useState('');
    const [logs, setLogs] = useState([]);
    
    const logsEndRef = useRef(null);

    // Otomatis melakukan scroll ke bagian bawah terminal setiap kali ada log baru masuk
    useEffect(() => {
        logsEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [logs]);

    // --- LANGKAH 1: MEMULIHKAN KONEKSI STATE SAAT HALAMAN DI-REFRESH ---
    useEffect(() => {
        const savedJobId = localStorage.getItem('jira_sync_job_id');
        const savedStatus = localStorage.getItem('jira_sync_status');
        const savedProgress = localStorage.getItem('jira_sync_progress');
        const savedLogs = localStorage.getItem('jira_sync_logs');

        // Pulihkan nilai input code pattern dari local storage jika ada
        const savedPattern = localStorage.getItem('jira_sync_pattern');
        if (savedPattern) setCodePattern(savedPattern);

        if (savedJobId && savedStatus === 'processing') {
            setStatus('processing');
            if (savedProgress) setProgress(parseInt(savedProgress));
            if (savedLogs) setLogs(JSON.parse(savedLogs));

            // Hubungkan kembali pipa Laravel Echo ke channel yang terputus
            connectWebSocket(savedJobId);
        }
    }, []);

    // --- LANGKAH 2: FUNGSI FOKUS UNTUK PENGAMATAN WEBSOCKET REVERB ---
    const connectWebSocket = (jobId) => {
        window.Echo.channel(`task.${jobId}`)
            .listen('TaskProgressUpdated', (event) => {
                // Update Progres Bar
                setProgress(event.progress);
                localStorage.setItem('jira_sync_progress', event.progress);

                // Jika Event membawa data logMessage dari JiraService, tampilkan di konsol
                if (event.logMessage) {
                    const timestamp = new Date().toLocaleTimeString();
                    const newLog = `[${timestamp}] ${event.logMessage}`;
                    
                    setLogs((prevLogs) => {
                        const updatedLogs = [...prevLogs, newLog];
                        localStorage.setItem('jira_sync_logs', JSON.stringify(updatedLogs));
                        return updatedLogs;
                    });
                }

                // Proses selesai total (100%)
                if (event.progress >= 100) {
                    setStatus('completed');
                    
                    // Bersihkan seluruh pelacakan localStorage demi efisiensi storage browser
                    localStorage.removeItem('jira_sync_job_id');
                    localStorage.removeItem('jira_sync_status');
                    localStorage.removeItem('jira_sync_progress');
                    localStorage.removeItem('jira_sync_logs');
                    localStorage.removeItem('jira_sync_pattern');
                    
                    // Putuskan langganan WebSocket Reverb
                    window.Echo.leave(`task.${jobId}`);
                }
            });
    };

    const startAsyncProcess = async () => {
        setStatus('processing');
        setProgress(0);
        
        const initialLog = `[${new Date().toLocaleTimeString()}] Menginisialisasi koneksi sinkronisasi Jira untuk pattern: ${codePattern}...`;
        setLogs([initialLog]);

        try {
            // 1. Trigger aksi async ke backend dengan mengirim parameter codepattern
            const response = await window.axios.post(route('task.trigger'), { codepattern: codePattern });
            const { jobId } = response.data;

            // 2. Kunci state ke dalam localStorage agar kebal terhadap F5 / Reload halaman
            localStorage.setItem('jira_sync_job_id', jobId);
            localStorage.setItem('jira_sync_status', 'processing');
            localStorage.setItem('jira_sync_progress', '0');
            localStorage.setItem('jira_sync_logs', JSON.stringify([initialLog]));
            localStorage.setItem('jira_sync_pattern', codePattern);

            // 3. Mulai mendengarkan Reverb
            connectWebSocket(jobId);

        } catch (error) {
            console.error('Proses gagal dipicu:', error);
            setStatus('idle');
            setLogs((prev) => [...prev, '[ERROR] Gagal memicu eksekusi asinkronus ke server Laravel Sail.']);
        }
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Jira Tracker Monolith Console</h2>}
        >
            <Head title="Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                        
                        {/* INPUT CODE PATTERN */}
                        <div className="mb-6">
                            <label className="block text-sm font-medium text-gray-700">Pattern (contoh: 1-5,7)</label>
                            <input
                                type="text"
                                value={codePattern}
                                disabled={status === 'processing'}
                                onChange={(e) => setCodePattern(e.target.value)}
                                placeholder="1-5,7"
                                className="mt-1 w-full rounded border-gray-300 shadow-sm disabled:bg-gray-100 disabled:text-gray-500"
                            />
                        </div>

                        {/* TRIGGER BUTTON */}
                        <div className="mb-6">
                            <button
                                onClick={startAsyncProcess}
                                disabled={status === 'processing'}
                                className="rounded bg-indigo-600 px-4 py-2 text-white font-medium hover:bg-indigo-700 disabled:bg-gray-400 dynamic-transition"
                            >
                                {status === 'processing' ? 'Sinkronisasi Sedang Berjalan...' : 'Get Data Jira'}
                            </button>
                        </div>

                        {/* PROGRES BAR & LIVE LOG CONSOLE TERMINAL */}
                        {status !== 'idle' && (
                            <div className="w-full max-w-3xl animate-fade-in">
                                <div className="flex justify-between mb-1">
                                    <span className="text-sm font-medium text-indigo-700">
                                        {status === 'processing' ? 'Proses Real-time Background' : 'Sinkronisasi Selesai!'}
                                    </span>
                                    <span className="text-sm font-medium text-indigo-700">{progress}%</span>
                                </div>
                                <div className="w-full bg-gray-200 rounded-full h-4 mb-6">
                                    <div
                                        className="bg-indigo-600 h-4 rounded-full transition-all duration-150 ease-out"
                                        style={{ width: `${progress}%` }}
                                    ></div>
                                </div>

                                {/* LIVE LOG TERMINAL DISPLAY */}
                                <div className="w-full bg-gray-950 text-emerald-400 font-mono text-xs rounded-lg p-4 h-72 overflow-y-auto shadow-2xl border border-gray-800">
                                    <div className="text-gray-500 mb-2 border-b border-gray-800 pb-1">//--- LIVE CONSOLE FEED FROM JIRASERVICE ---</div>
                                    {logs.map((log, index) => (
                                        <div key={index} className="mb-1 leading-relaxed tracking-wide">
                                            {log}
                                        </div>
                                    ))}
                                    {/* Jangkar otomatis scroll */}
                                    <div ref={logsEndRef} />
                                </div>
                            </div>
                        )}

                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
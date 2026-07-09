import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';

export default function Dashboard() {
    const [progress, setProgress] = useState(0);
    const [status, setStatus] = useState('idle'); // idle, processing, completed
    const [start, setStart] = useState(0);
    const [end, setEnd] = useState(0);

    const startAsyncProcess = async () => {
        setStatus('processing');
        setProgress(0);

        try {
            // 1. Trigger aksi async ke backend
            const response = await window.axios.post(route('task.trigger'), { start, end });
            const { jobId } = response.data;

            // 2. Berlangganan ke channel Reverb secara real-time
            window.Echo.channel(`task.${jobId}`)
                .listen('TaskProgressUpdated', (event) => {
                    setProgress(event.progress);

                    if (event.progress >= 100) {
                        setStatus('completed');
                        // Putuskan langganan jika sudah selesai untuk menghemat resource
                        window.Echo.leave(`task.${jobId}`);
                    }
                });

        } catch (error) {
            console.error('Proses gagal dipicu:', error);
            setStatus('idle');
        }
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Jira Tracker</h2>}
        >
            <Head title="Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                        
                        <div className="mb-6 flex gap-4">
                            <div className="flex-1">
                                <label className="block text-sm font-medium text-gray-700">Awal</label>
                                <input
                                    type="number"
                                    value={start}
                                    onChange={(e) => setStart(Number(e.target.value))}
                                    className="mt-1 w-full rounded border-gray-300 shadow-sm"
                                />
                            </div>
                            <div className="flex-1">
                                <label className="block text-sm font-medium text-gray-700">Akhir</label>
                                <input
                                    type="number"
                                    value={end}
                                    onChange={(e) => setEnd(Number(e.target.value))}
                                    className="mt-1 w-full rounded border-gray-300 shadow-sm"
                                />
                            </div>
                        </div>

                        <div className="mb-6">
                            <button
                                onClick={startAsyncProcess}
                                disabled={status === 'processing'}
                                className="rounded bg-indigo-600 px-4 py-2 text-white font-medium hover:bg-indigo-700 disabled:bg-gray-400"
                            >
                                {status === 'processing' ? 'Sedang Memproses Latar Belakang...' : 'Get Data Jira'}
                            </button>
                        </div>

                        {status !== 'idle' && (
                            <div className="w-full max-w-xl">
                                <div className="flex justify-between mb-1">
                                    <span className="text-sm font-medium text-indigo-700">
                                        {status === 'processing' ? 'Proses Berjalan' : 'Proses Selesai!'}
                                    </span>
                                    <span className="text-sm font-medium text-indigo-700">{progress}%</span>
                                </div>
                                <div className="w-full bg-gray-200 rounded-full h-4">
                                    <div
                                        className="bg-indigo-600 h-4 rounded-full transition-all duration-100 ease-out"
                                        style={{ width: `${progress}%` }}
                                    ></div>
                                </div>
                            </div>
                        )}

                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

import { useEffect, useState } from "react";
import apiService from "@services/apiService";

export default function Questions() {
    const [status, setStatus] = useState("idle"); // idle | loading | ready | error
    const [error, setError] = useState(null);
    const [questionnaire, setQuestionnaire] = useState(null);

    useEffect(() => {
        const controller = new AbortController();

        const load = async () => {
            setStatus("loading");
            setError(null);
            try {
                const res = await apiService.get("/api/me/questions", { signal: controller.signal });
                const json = res?.data ?? res;

                setQuestionnaire(json);
                setStatus("ready");
            } catch (e) {
                setError(e?.response?.data || e.message);
                setStatus("error");
            }
        };

        load();
        return () => controller.abort();
    }, []);

    return (
        <main className="min-h-screen bg-base-200 py-8">
            <div className="max-w-3xl mx-auto px-4">
                <header className="mb-6">
                    <h1 className="text-2xl font-semibold">
                        {questionnaire?.title ?? "Questions"}
                    </h1>
                    <p className="text-sm opacity-70">
                        Répondez pour générer votre playlist.
                    </p>
                    <p className="text-xs mt-2 opacity-60">
                        status: <code>{status}</code> — questions:{" "}
                        <code>{Array.isArray(questionnaire?.questions) ? questionnaire.questions.length : 0}</code>
                    </p>
                </header>

                {status === "loading" && (
                    <div className="alert">
                        <span>Chargement…</span>
                    </div>
                )}

                {status === "error" && (
                    <div className="alert alert-error">
                        <span>{typeof error === "string" ? error : "Une erreur est survenue."}</span>
                    </div>
                )}

                {status === "ready" && Array.isArray(questionnaire?.questions) && (
                    <section className="space-y-4">
                        {questionnaire.questions.map((q) => (
                            <article key={q.id} className="card bg-base-100 shadow">
                                <div className="card-body">
                                    <div className="flex items-center justify-between gap-2 mb-2">
                                        <h3 className="card-title text-base">{q.label}</h3>
                                        <span className="badge badge-neutral">{q.type}</span>
                                    </div>

                                    {Array.isArray(q.options) && q.options.length > 0 ? (
                                        <ul className="list-disc list-inside space-y-1">
                                            {q.options.map((opt) => (
                                                <li key={opt.id}>{opt.label}</li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <p className="text-sm opacity-70">Aucune option.</p>
                                    )}
                                </div>
                            </article>
                        ))}

                        {/* Bouton “Générer” plus tard quand tu enverras les réponses */}
                        <div className="mt-4">
                            <button className="btn btn-primary" disabled>
                                Générer une playlist (à venir)
                            </button>
                        </div>
                    </section>
                )}
            </div>
        </main>
    );
}
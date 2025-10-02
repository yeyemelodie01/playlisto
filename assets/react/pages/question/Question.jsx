import { useEffect, useState } from "react";
import apiService from "@services/apiService";
import Header from "@components/Header";

export default function Questions() {
    const [status, setStatus] = useState("idle"); // idle | loading | ready | error
    const [error, setError] = useState(null);
    const [questionnaire, setQuestionnaire] = useState(null);
    const [currentIndex, setCurrentIndex] = useState(0);

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
        <>
            <Header />
            <main className="min-h-screen bg-base-200 py-8">
                <div className="max-w-3xl mx-auto px-4">
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
                        <section className="flex flex-col items-center justify-center min-h-[60vh]">
                            {questionnaire.questions[currentIndex] && (
                                <>
                                    <h2 className="text-xl font-semibold mb-8 text-center">{questionnaire.questions[currentIndex].label}</h2>
                                    {Array.isArray(questionnaire.questions[currentIndex].options) &&
                                    questionnaire.questions[currentIndex].options.length > 0 ? (
                                        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                                            {questionnaire.questions[currentIndex].options.map((opt) => (
                                                <button
                                                    key={opt.id}
                                                    className="btn btn-outline rounded-lg text-center"
                                                >
                                                    {opt.label}
                                                </button>
                                            ))}
                                        </div>
                                    ) : (
                                        <p className="text-sm opacity-70">Aucune option.</p>
                                    )}
                                </>
                            )}

                            <div className="flex justify-end w-full mt-10">
                                {currentIndex < questionnaire.questions.length - 1 ? (
                                    <button className="btn btn-primary" onClick={() => setCurrentIndex((i) => i + 1)}>
                                        Suivant
                                    </button>
                                ) : (
                                    <button className="btn btn-accent">
                                        Générer une playlist
                                    </button>
                                )}
                            </div>
                        </section>
                    )}
                </div>
            </main>
        </>
    );
}
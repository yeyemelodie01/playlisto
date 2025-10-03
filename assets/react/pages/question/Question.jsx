import { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import apiService from "@services/apiService";
import Header from "@components/Header";

const activityMap = Object.freeze({
    sport: "sport",
    travail: "work",
    detente: "relax",
    etude: "study",
    cuisine: "cooking",
});

/**
 * Normalize a question's options to an array of { key: string|number, label: string }.
 * Accepts either:
 *  - [{ id, label }, ...] OR
 *  - ["oui", "non", ...]
 */
function normalizeOptions(options) {
    if (!Array.isArray(options)) return [];
    return options.map((opt, idx) => {
        if (opt && typeof opt === "object") {
            const key = opt.id ?? idx;
            const label = String(opt.label ?? opt.value ?? opt.name ?? key);
            return { key, label };
        }
        return { key: idx, label: String(opt) };
    });
}

function normalizeType(t) {
    if (!t) return 'single';
    const v = String(t).toLowerCase();
    if (v.includes('multi')) return 'multiple';
    if (v.includes('single')) return 'single';
    return v === 'multiple' ? 'multiple' : 'single';
}

function stripDiacritics(str = "") {
    return String(str)
        .normalize("NFD")
        .replace(/\p{Diacritic}/gu, "")
        .toLowerCase();
}

function isActivityQuestion(q) {
    if (!q) return false;
    const label = stripDiacritics(q.label || q.questionLabel || "");
    if (label.includes("activite")) return true; // match "activité" or "activite"

    // Fallback: check options look like the known activity set
    const opts = Array.isArray(q.options) ? q.options : [];
    const labels = opts.map((o) => (typeof o === "object" ? (o.label ?? o.value ?? o.name ?? "") : o));
    const norm = labels.map((l) => stripDiacritics(String(l)));
    const activitySet = new Set(["sport", "travail", "detente", "etude", "cuisine"]);
    const overlap = norm.filter((l) => activitySet.has(l));
    return overlap.length >= 3; // heuristic
}

/**
 * Build the payload expected by the backend:
 * {
 *   surveyId: number,
 *   answers: [
 *     { questionId: number, optionValue: string } | { questionId: number, optionValues: string[] }
 *   ]
 * }
 */
function buildSubmissionPayload(questionnaire, selected) {
    const answers = [];
    let activityQId = null;

    for (const q of questionnaire.questions || []) {
        const qid = q.id ?? q.questionId ?? null;
        if (!qid) continue;

        if (!activityQId && isActivityQuestion(q)) {
            activityQId = qid;
        }

        const qType = normalizeType(q.type || q.questionType || 'single');
        const selectedForQ = selected[qid];

        if (qType === "multiple") {
            const values = Array.isArray(selectedForQ) ? selectedForQ : [];
            answers.push({ questionId: qid, optionValues: values });
        } else {

            if (typeof selectedForQ === "string") {
                let value = selectedForQ;

                const isActivity = (activityQId && qid === activityQId) || isActivityQuestion(q);
                if (isActivity) {
                    const key = stripDiacritics(value.trim());
                    if (activityMap[key]) {
                        value = activityMap[key];
                    }
                }

                answers.push({ questionId: qid, optionValue: value });
            }
        }
    }

    if (activityQId) {
        for (const a of answers) {
            if (a.questionId === activityQId && typeof a.optionValue === "string") {
                const k = stripDiacritics(a.optionValue.trim());
                if (activityMap[k]) a.optionValue = activityMap[k];
            }
        }
    }

    return {
        surveyId: questionnaire.id ?? questionnaire.surveyId ?? 1,
        answers,
    };
}

export default function Questions() {
    const navigate = useNavigate();

    const [status, setStatus] = useState("idle");
    const [error, setError] = useState(null);
    const [questionnaire, setQuestionnaire] = useState(null);

    const [currentIndex, setCurrentIndex] = useState(0);
    const [selected, setSelected] = useState({}); // { [questionId]: string | string[] }

    // Load the active questionnaire
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

    const questions = questionnaire?.questions ?? [];
    const currentQuestion = questions[currentIndex];

    const options = useMemo(() => normalizeOptions(currentQuestion?.options), [currentQuestion]);

    const qId = currentQuestion?.id ?? currentQuestion?.questionId;
    const qType = normalizeType(currentQuestion?.type || currentQuestion?.questionType || 'single');
    const currentValue = selected[qId];

    const canContinue = useMemo(() => {
        if (!currentQuestion) return false;
        if (qType === "multiple") {
            return Array.isArray(currentValue) && currentValue.length > 0;
        }
        return typeof currentValue === "string" && currentValue.length > 0;
    }, [currentQuestion, qType, currentValue]);

    // Handlers
    const onPickSingle = (label) => {
        if (!qId) return;
        console.log('[onPickSingle]', { qId, label });
        const clean = String(label).trim();
        setSelected((prev) => ({ ...prev, [qId]: clean }));
    };

    const onToggleMulti = (label) => {
        if (!qId) return;
        console.log('[onToggleMulti]', { qId, label });
        setSelected((prev) => {
            const prevArr = Array.isArray(prev[qId]) ? prev[qId] : [];
            const clean = String(label).trim();
            const exists = prevArr.includes(clean);
            const next = exists ? prevArr.filter((l) => l !== clean) : [...prevArr, clean];
            return { ...prev, [qId]: next };
        });
    };

    const goNext = () => {
        if (currentIndex < questions.length - 1) {
            console.log('[goNext] from', currentIndex, 'selected so far:', selected);
            setCurrentIndex((i) => i + 1);
        }
    };

    const goPrev = () => {
        if (currentIndex > 0) {
            console.log('[goPrev] from', currentIndex, 'selected so far:', selected);
            setCurrentIndex((i) => i - 1);
        }
    };

    const submitAll = async () => {
        if (!questionnaire) return;
        try {
            setStatus("submitting");
            setError(null);

            // 1) Submit answers
            const payload = buildSubmissionPayload(questionnaire, selected);
            console.log("[submitAll] Payload (with activity mapped):", payload);
            const submitRes = await apiService.post("/api/me/surveys/submit", payload);
            const submitData = submitRes?.data ?? submitRes;
            console.log("[submitAll] Submit response:", submitData);

            const submissionId = submitData?.submission_id ?? submitData?.id;
            if (!submissionId) {
                throw new Error("Impossible de récupérer l'identifiant de soumission.");
            }

            // 2) Generate playlist from submission
            const genRes = await apiService.post("/api/me/generate-playlist", {
                submission_id: submissionId,
                limit: 20,
            });
            const genData = genRes?.data ?? genRes;
            console.log("[submitAll] Generate response:", genData);

            // Try to find the created playlist id in various shapes
            const playlistId =
                genData?.id ||
                genData?.playlist?.id ||
                genData?.data?.id;

            if (!playlistId) {
                // If backend returns the full playlist object, navigate directly with state
                return navigate("/playlist" +
                    "", { state: { generated: genData } });
            }

            navigate(`/playlist/${playlistId}`);
        } catch (e) {
            setError(e?.response?.data || e.message);
            setStatus("ready");
        }
    };

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

                    {status === "submitting" && (
                        <div className="flex flex-col items-center justify-center min-h-[50vh] gap-4">
                            <span className="loading loading-ring loading-lg" />
                            <p className="opacity-80 text-center">
                                On analyse vos réponses avec l'IA… puis on génère votre playlist 🎧
                            </p>
                        </div>
                    )}

                    {status === "ready" && Array.isArray(questions) && questions.length > 0 && currentQuestion && (
                        <section className="flex flex-col items-center justify-center min-h-[60vh]">
                            <h2 className="text-2xl font-semibold mb-8 text-center">
                                {currentQuestion.label}
                            </h2>

                            {options.length > 0 ? (
                                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                                    {options.map((opt) => {
                                        const isActive =
                                            qType === "multiple"
                                                ? Array.isArray(currentValue) && currentValue.includes(opt.label)
                                                : currentValue === opt.label;

                                        const baseBtn = "btn rounded-lg text-center";
                                        const variant =
                                            qType === 'multiple'
                                                ? (isActive ? 'btn-secondary btn-active' : 'btn-outline')
                                                : (isActive ? 'btn-primary btn-active' : 'btn-outline');

                                        return (
                                            <button
                                                key={opt.key}
                                                type="button"
                                                className={`${baseBtn} ${variant}`}
                                                onClick={() => (qType === "multiple" ? onToggleMulti(opt.label) : onPickSingle(opt.label))}
                                            >
                                                {opt.label}
                                            </button>
                                        );
                                    })}
                                </div>
                            ) : (
                                <p className="text-sm opacity-70">Aucune option.</p>
                            )}

                            <div className="flex items-center justify-between w-full mt-10">
                                <button
                                    className="btn btn-ghost"
                                    onClick={goPrev}
                                    disabled={currentIndex === 0}
                                >
                                    Précédent
                                </button>

                                {currentIndex < questions.length - 1 ? (
                                    <button
                                        className="btn btn-primary"
                                        onClick={goNext}
                                        disabled={!canContinue}
                                    >
                                        Suivant
                                    </button>
                                ) : (
                                    <button
                                        className="btn btn-accent"
                                        onClick={submitAll}
                                        disabled={!canContinue}
                                    >
                                        Générer une playlist
                                    </button>
                                )}
                            </div>
                        </section>
                    )}

                    {status === "ready" && Array.isArray(questions) && questions.length === 0 && (
                        <div className="alert">
                            <span>Aucune question disponible.</span>
                        </div>
                    )}
                </div>
            </main>
        </>
    );
}
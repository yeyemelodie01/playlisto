import { useMemo, useState } from "react";
import { Link } from "react-router-dom";

export default function Signin({ redirectTo = "/", onLoggedIn }) {
    const [email, setEmail] = useState("");
    const [username, setUsername] = useState("");
    const [password, setPassword] = useState("");
    const [showPwd, setShowPwd] = useState(false);
    const [errors, setErrors] = useState({});
    const [alert, setAlert] = useState({ type: null, message: "" });
    const [loading, setLoading] = useState(false);

    const canSubmit = useMemo(() => {
        return email.trim() !== "" && password.length >= 8 && !loading;
    }, [email, password, loading]);

    const validateClient = () => {
        const next = {};
        const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!email.trim()) next.email = "Email requis";
        else if (!emailRe.test(email.trim())) next.email = "Email invalide";

        if (username && (username.length < 2 || username.length > 50)) {
            next.username = "Entre 2 et 50 caractères";
        }

        if (!password) next.password = "Mot de passe requis";
        else if (password.length < 8) next.password = "8 caractères minimum";

        setErrors(next);
        return Object.keys(next).length === 0;
    };

    const loginAfterSignup = async (email, password) => {
        const res = await fetch("/api/authentication_token", {
            method: "POST",
            headers: { "Content-Type": "application/json", Accept: "application/json" },
            body: JSON.stringify({ email, password }),
        });

        if (!res.ok) {
            let data = {};
            try { data = await res.json(); } catch {}
            throw new Error(data?.message || `Login failed (HTTP ${res.status})`);
        }

        const data = await res.json();
        const token = data?.token || data?.id_token || data?.jwt;
        if (!token) throw new Error("No token returned by auth endpoint");

        localStorage.setItem("authToken", token);
        localStorage.setItem("authEmail", email);

        if (typeof onLoggedIn === "function") onLoggedIn(token);
        window.location.assign(redirectTo);
    };

    const onSubmit = async (e) => {
        e.preventDefault();
        setAlert({ type: null, message: "" });
        if (!validateClient()) return;

        setLoading(true);
        try {
            const res = await fetch("/api/signup", {
                method: "POST",
                headers: { "Content-Type": "application/json", Accept: "application/json" },
                body: JSON.stringify({ email: email.trim(), username: username.trim() || undefined, password }),
            });

            let data = {};
            try { data = await res.json(); } catch {}

            if (res.status === 201 && (data?.status === "ok" || data?.message)) {
                try {
                    await loginAfterSignup(email.trim(), password);
                    return;
                } catch (authErr) {
                    setAlert({ type: "bad", message: authErr?.message || "Authentification impossible après l'inscription." });
                    return;
                }
            }

            if (res.status === 409 && data?.error === "email_already_used") {
                setErrors((prev) => ({ ...prev, email: data.message || "Email déjà utilisé" }));
                setAlert({ type: "bad", message: "Impossible de créer le compte." });
                return;
            }

            if (res.status === 422 && data?.violations) {
                const mapped = {};
                Object.entries(data.violations).forEach(([path, msgs]) => {
                    const key = String(path).replace(/[^a-z]/gi, "").toLowerCase();
                    const msg = Array.isArray(msgs) ? msgs.join(" ") : String(msgs);
                    if (key === "email" || key === "password" || key === "username") {
                        mapped[key] = msg;
                    }
                });
                setErrors(mapped);
                setAlert({ type: "bad", message: "Merci de corriger les erreurs." });
                return;
            }

            setAlert({ type: "bad", message: data?.message || "Erreur inattendue, réessayez." });
        } catch {
            setAlert({ type: "bad", message: "Erreur réseau. Vérifiez votre connexion." });
        } finally {
            setLoading(false);
        }
    };

    return (
        <>
            <main className="min-h-screen bg-base-200 grid place-items-center p-4">
                <div className="card w-[40%] max-w-md bg-base-100 shadow-xl border border-base-300">
                    <div className="card-body flex items-center">
                        <div className="flex items-center flex-col">
                            <img src="/images/playlisto-logo.png" alt="Logo" className="w-64 px-4 py-2" />
                            <h1 className="text-xl text-center my-4">S'inscrire</h1>
                        </div>

                        {alert.type && (
                            <div className={`alert ${alert.type === "ok" ? "alert-success" : "alert-error"} mt-3`}>
                                <span>{alert.message}</span>
                            </div>
                        )}

                        <form className="mt-4 space-y-4 w-[70%]" onSubmit={onSubmit} noValidate>
                            <fieldset className="fieldset mx-auto">
                                <legend className="fieldset-legend">Pseudonyme</legend>
                                <input
                                    id="username"
                                    type="text"
                                    value={username}
                                    onChange={(e) => setUsername(e.target.value)}
                                    className={`input input-bordered ${errors.username ? "input-error" : ""} validator w-full`}
                                    placeholder="Entrez votre pseudo"
                                    pattern="[A-Za-z][A-Za-z0-9\-]*"
                                    minLength="3"
                                    maxLength="30"
                                    title="Only letters, numbers or dash"
                                />
                                {errors.username && <label className="label"><span
                                    className="label-text-alt text-error">{errors.username}</span></label>}
                            </fieldset>
                            <fieldset className="fieldset mx-auto">
                                <legend className="fieldset-legend">Email</legend>
                                <input
                                    id="email"
                                    type="email"
                                    value={email}
                                    required
                                    onChange={(e) => setEmail(e.target.value)}
                                    className={`input input-bordered ${errors.email ? "input-error" : ""} validator w-full`}
                                    pattern="[a-z0-9]+@[a-z0-9]+.{8,}"
                                    placeholder="you@example.com"
                                    autoComplete="email"
                                />
                                {errors.email && <label className="label"><span
                                    className="label-text-alt text-error">{errors.email}</span></label>}
                            </fieldset>
                            <fieldset className="fieldset mx-auto">
                              <legend className="fieldset-legend">Mot de passe</legend>
                              <div className="relative w-full">
                                <input
                                  id="password"
                                  type={showPwd ? "text" : "password"}
                                  value={password}
                                  onChange={(e) => setPassword(e.target.value)}
                                  className={`input input-bordered validator w-full pr-12 ${errors.password ? "input-error" : ""}`}
                                  placeholder="Entrez votre mot de passe"
                                  pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}"
                                  title="Must be more than 8 characters, including number, lowercase letter, uppercase letter"
                                />
                                <button
                                  type="button"
                                  className="absolute top-1/2 right-3 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                                  onClick={() => setShowPwd((s) => !s)}
                                  aria-label={showPwd ? "Masquer le mot de passe" : "Afficher le mot de passe"}
                                >
                                  {showPwd ? (
                                    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-5 0-9-4-9-4s1.5-2.5 4-4m5-2a3 3 0 11-4.243 4.243A3 3 0 0112 9zm0 0c.345-.021.687-.032 1.027-.032 4.63 0 8.39 3.1 10.3 5.032-1.14 1.182-2.53 2.316-4.183 3.301" />
                                    </svg>
                                  ) : (
                                    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm7.5 0s-3.5 7-10.5 7S1.5 12 1.5 12 5 5 12 5s10.5 7 10.5 7z" />
                                    </svg>
                                  )}
                                </button>
                              </div>
                              {errors.password && <label className="label"><span className="label-text-alt text-error">{errors.password}</span></label>}
                            </fieldset>
                            <button
                                type="submit"
                                className={`btn btn-primary w-full ${loading ? "loading" : ""}`}
                                disabled={!canSubmit}
                            >
                                {loading ? "Création en cours…" : "Créer mon compte"}
                            </button>
                        </form>

                        <p className="text-xs text-base-content/60 mt-4">Vous avez déja un compte?{" "}
                            <Link to="/login" className="text-sky-400 font-bold">connecter-vous</Link>
                        </p>
                    </div>
                </div>
            </main>
        </>
    )
}
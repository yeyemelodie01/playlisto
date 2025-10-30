import { useMemo, useState } from "react";

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
                <div className="card w-full max-w-md bg-base-100 shadow-xl border border-base-300">
                    <div className="card-body">
                        <div className="flex justify-center">
                            <img src="/images/playlisto-logo.png" alt="Logo" className="w-64 px-4 py-2" />
                            <h1 className="text-xl text-center my-4">S'inscrire</h1>
                        </div>

                        {alert.type && (
                            <div className={`alert ${alert.type === "ok" ? "alert-success" : "alert-error"} mt-3`}>
                                <span>{alert.message}</span>
                            </div>
                        )}

                        <form className="mt-4 space-y-4" onSubmit={onSubmit} noValidate>
                            <div className="form-control">
                                <label htmlFor="email" className="label">
                                    <span className="label-text">Email</span>
                                </label>
                                <input
                                    id="email"
                                    type="email"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    className={`input input-bordered ${errors.email ? "input-error" : ""}`}
                                    placeholder="you@example.com"
                                    autoComplete="email"
                                />
                                {errors.email && <label className="label"><span className="label-text-alt text-error">{errors.email}</span></label>}
                            </div>

                            <div className="form-control">
                                <label htmlFor="username" className="label">
                                    <span className="label-text">Pseudo</span>
                                </label>
                                <input
                                    id="username"
                                    type="text"
                                    value={username}
                                    onChange={(e) => setUsername(e.target.value)}
                                    className={`input input-bordered ${errors.username ? "input-error" : ""}`}
                                    placeholder="Entrez votre pseudo"
                                    autoComplete="nickname"
                                />
                                {errors.username && <label className="label"><span className="label-text-alt text-error">{errors.username}</span></label>}
                            </div>

                            <div className="form-control">
                                <label htmlFor="password" className="label">
                                    <span className="label-text">Mot de passe</span>
                                </label>
                                <div className="input-group">
                                    <input
                                        id="password"
                                        type={showPwd ? "text" : "password"}
                                        value={password}
                                        onChange={(e) => setPassword(e.target.value)}
                                        className={`input input-bordered w-full ${errors.password ? "input-error" : ""}`}
                                        placeholder="Entrez votre mot de passe"
                                        autoComplete="new-password"
                                    />
                                    <button
                                        type="button"
                                        className="btn btn-ghost"
                                        onClick={() => setShowPwd((s) => !s)}
                                        aria-label={showPwd ? "Masquer le mot de passe" : "Afficher le mot de passe"}
                                    >
                                        {showPwd ? "Masquer" : "Afficher"}
                                    </button>
                                </div>
                                {errors.password && <label className="label"><span className="label-text-alt text-error">{errors.password}</span></label>}
                            </div>

                            <button
                                type="submit"
                                className={`btn btn-primary w-full ${loading ? "loading" : ""}`}
                                disabled={!canSubmit}
                            >
                                {loading ? "Création en cours…" : "Créer mon compte"}
                            </button>
                        </form>

                        <p className="text-xs text-base-content/60 mt-4">Vous avez déja un compte? <a href="/login">connecter-vous</a></p>
                    </div>
                </div>
            </main>
        </>
    )
}
import Header from "@components/Header";
import MenuAside from "@components/MenuAside";
import {useEffect, useState} from "react";
import apiService from "@services/apiService";
import Footer from "@components/Footer";

export default function Profile() {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [alert, setAlert] = useState({ type: "", message: "" });
    const [username, setUsername] = useState("");
    const [email, setEmail] = useState("");
    const [password, setPassword] = useState("");
    const [showPwd, setShowPwd] = useState(false);
    const [errors, setErrors] = useState({ email: null, username: null, password: null });

    useEffect(() => {
        const controller = new AbortController();
        let mounted = true;

        const load = async () => {
            setLoading(true);
            try {
                const res = await apiService.get("/api/me", { signal: controller.signal });
                const data = res.data || {};
                if (!mounted) return;

                setEmail(data.email ?? "");
                const fallbackUsername = data.email?.split?.("@")?.[0] ?? "";
                setUsername(data.username ?? fallbackUsername);
            } catch (e) {
                if (mounted && e.name !== 'CanceledError') {
                    setAlert({ type: "error", message: "Impossible de récupérer le profil." });
                }
            } finally {
                if (mounted) setLoading(false);
            }
        };

        load();

        return () => {
            mounted = false;
            controller.abort();
        };
    }, []);

    const validate = () => {
        const next = { email: null, username: null, password: null };
        const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!email || !emailRe.test(email)) next.email = "Email invalide";
        if (username && (username.length < 2 || username.length > 50)) next.username = "Entre 2 et 50 caractères";
        if (password && password.length > 0 && password.length < 8) next.password = "8 caractères minimum";

        setErrors(next);
        return !next.email && !next.username && !next.password;
    };

    const onSubmit = async (e) => {
        e.preventDefault();
        setAlert({ type: "", message: "" });

        if (!validate()) {
            setAlert({ type: "error", message: "Corrigez les erreurs du formulaire." });
            return;
        }

        setSaving(true);
        try {
            const payload = {};
            if (username !== "") payload.username = username;
            if (email !== "") payload.email = email;
            if (password !== "") payload.password = password;

            const res = await apiService.put("/api/me", payload);
            if (res.status === 200 || res.status === 204) {
                setAlert({ type: "success", message: "Profil mis à jour." });
                setPassword("");
            } else {
                setAlert({ type: "error", message: "Erreur lors de la sauvegarde." });
            }
        } catch (err) {
            const msg =
                err?.response?.data?.message ?? err?.message ?? "Erreur réseau";
            setAlert({ type: "error", message: msg });
        } finally {
            setSaving(false);
        }
    };

    const onDelete = async () => {
        if (!confirm("Confirmer la suppression de votre compte ? Cette action est irréversible.")) return;

        setDeleting(true);
        setAlert({ type: "", message: "" });

        try {
            const res = await apiService.delete("/api/me");
            if (res.status === 200 || res.status === 204) {
                setAlert({ type: "success", message: "Compte supprimé. Redirection…" });
                setTimeout(() => {
                    apiService.logout();
                }, 300);
            } else {
                setAlert({ type: "error", message: "Impossible de supprimer le compte." });
                setDeleting(false);
            }
        } catch (err) {
            const msg = err?.response?.data?.message ?? err?.message ?? "Erreur réseau";
            setAlert({ type: "error", message: msg });
            setDeleting(false);
        }
    };

    return (
        <>
            <Header />
            <main className="h-[44.8rem] grid lg:grid-cols-5 sm:grid-cols-3 gap-4">
                <MenuAside />

                <section className="col-span-4 w-full mx-auto px-4 overflow-auto mt-4">
                  <h1 className="text-2xl font-semibold mb-6">Mon profil</h1>
                    {alert.message && (
                        <div
                            className={`alert ${
                                alert.type === "success" ? "alert-success" : "alert-error"
                            } mb-4`}
                        >
                            <span>{alert.message}</span>
                        </div>
                    )}

                    {loading ? (
                        <div className="text-center py-10">Chargement…</div>
                    ) : (
                        <form className="space-y-6 max-w-xl" onSubmit={onSubmit} noValidate>

                            <div className="form-control">
                                <label className="label">
                                    <span className="label-text">Nom d’utilisateur</span>
                                </label>
                                <input
                                    type="text"
                                    placeholder="Username"
                                    className={`input input-bordered w-full ${
                                        errors.username ? "input-error" : ""
                                    }`}
                                    value={username}
                                    onChange={(e) => setUsername(e.target.value)}
                                    autoComplete="username"
                                />
                                {errors.username && (
                                    <label className="label">
                                        <span className="label-text-alt text-error">
                                          {errors.username}
                                        </span>
                                    </label>
                                )}
                            </div>

                            <div className="form-control">
                                <label className="label">
                                    <span className="label-text">Adresse email</span>
                                </label>
                                <input
                                    type="email"
                                    placeholder="Email"
                                    className={`input input-bordered w-full ${
                                        errors.email ? "input-error" : ""
                                    }`}
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    autoComplete="email"
                                />
                                {errors.email && (
                                    <label className="label">
                                        <span className="label-text-alt text-error">
                                          {errors.email}
                                        </span>
                                    </label>
                                )}
                            </div>

                            <div className="form-control">
                                <label className="label">
                                  <span className="label-text">
                                    Mot de passe (laisser vide pour garder l'actuel)
                                  </span>
                                </label>
                                <div className="input-group">
                                    <input
                                        type={showPwd ? "text" : "password"}
                                        placeholder="Nouveau mot de passe"
                                        className={`input input-bordered w-full ${
                                            errors.password ? "input-error" : ""
                                        }`}
                                        value={password}
                                        onChange={(e) => setPassword(e.target.value)}
                                        autoComplete="new-password"
                                    />
                                </div>
                                {errors.password && (
                                    <label className="label">
                                        <span className="label-text-alt text-error">
                                          {errors.password}
                                        </span>
                                    </label>
                                )}
                            </div>

                            <div className="flex items-center gap-4">
                                <button
                                    type="submit"
                                    className={`btn btn-primary ${saving ? "loading" : ""}`}
                                    disabled={saving}
                                >
                                    {saving ? "Enregistrement…" : "Enregistrer"}
                                </button>

                                <button
                                    type="button"
                                    className={`btn btn-error ${deleting ? "loading" : ""}`}
                                    onClick={onDelete}
                                    disabled={deleting}
                                >
                                    {deleting ? "Suppression…" : "Supprimer le compte"}
                                </button>
                            </div>
                        </form>
                    )}
                </section>
            </main>

            <Footer />
        </>
    )
}
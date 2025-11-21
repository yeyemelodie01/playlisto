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
    const [currentPassword, setCurrentPassword] = useState("");
    const [confirmError, setConfirmError] = useState("");
    const [deleteError, setDeleteError] = useState("");

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
        setConfirmError("");

        if (!validate()) {
            setAlert({ type: "error", message: "Corrigez les erreurs du formulaire." });
            return;
        }

        const dialog = document.getElementById("confirm_modal");
        if (dialog){
            setCurrentPassword("");
            dialog.showModal();
        }
    };

    const handleConfirmUpdate = async (e) => {
        e.preventDefault();
        setConfirmError("");
        setAlert({ type: "", message: "" });

        if (!currentPassword) {
            setConfirmError("Mot de passe actuel requis !");

            return;
        }

        setSaving(true);
        try {
            const payload = { currentPassword };
            if (username !== "") payload.username = username;
            if (email !== "") payload.email = email;
            if (password !== "") payload.password = password;

            const res = await apiService.put("/api/me", payload);
            if (res.status === 200 || res.status === 204) {
                setAlert({ type: "success", message: "Profil mis à jour." });
                setPassword("");
                setCurrentPassword("");
                document.getElementById("confirm_modal")?.close();
            } else {
                setAlert({ type: "error", message: "Erreur lors de la sauvegarde." });
            }
        } catch (err) {
            const data = err?.response?.data;
            const msg = data?.["hydra:description"] || data?.message || err?.message || "Erreur réseau";

            setConfirmError(msg);
        } finally {
            setSaving(false);
        }
    }
    const onDelete = async () => {
        setAlert({ type: "", message: "" });

        const dialog = document.getElementById("deleteModal");
        if (dialog) {
            setCurrentPassword("");
            dialog.showModal();
        }
    };

    const handleConfirmDelete = async (e) => {
        e.preventDefault();
        setDeleteError("");
        setAlert({ type: "", message: "" });

        if (!currentPassword) {
            setDeleteError("Mot de passe actuel requis !");

            return;
        }

        setDeleting(true);

        try {
            const res = await apiService.delete("/api/me", { data: { currentPassword } });
            if (res.status === 200 || res.status === 204) {
                setAlert({ type: "success", message: "Compte supprimé. Redirection…" });
                document.getElementById("deleteModal")?.close();
                setTimeout(() => {
                    apiService.logout();
                }, 300);
            } else {
                setAlert({ type: "error", message: "Impossible de supprimer le compte." });
                setDeleting(false);
            }
        } catch (err) {
            const data = err?.response?.data;
            const msg = data?.["hydra:description"] || data?.message || err?.message || "Erreur réseau";

            setDeleteError(msg);
        } finally {
            setDeleting(false);
        }
    }

    return (
        <>
            <Header />
            <main className="h-[34.4rem] md:h-[33.99rem] grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                <MenuAside />

                <section className="col-span-4 w-full mx-auto px-4 overflow-auto mt-4">
                  <h1 className="text-2xl font-semibold mb-6">Mon profil</h1>
                    {alert.message && (
                        <div
                            className={`alert w-60 ${
                                alert.type === "success" ? "alert-success" : "alert-error"
                            } mb-4`}
                        >
                            <span>{alert.message}</span>
                        </div>
                    )}

                    {loading ? (
                        <div className="text-center py-10">Chargement…</div>
                    ) : (
                        <form className="space-y-6 max-w-xl w-80" noValidate>
                            <div className="form-control">
                                <label className="label">
                                    <span className="label-text">Nom d’utilisateur</span>
                                </label>
                                <input type="text" placeholder="Username" className={`input input-bordered w-full ${errors.username ? "input-error" : ""}`} value={username} onChange={(e) => setUsername(e.target.value)} autoComplete="username"/>
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
                                <input type="email" placeholder="Email" className={`input input-bordered w-full ${errors.email ? "input-error" : ""}`} value={email} onChange={(e) => setEmail(e.target.value)} autoComplete="email"/>
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
                                    Nouveau mot de passe
                                  </span>
                                </label>
                                <div className="input-group">
                                    <input type={showPwd ? "text" : "password"} placeholder="Nouveau mot de passe" className={`input input-bordered w-full ${errors.password ? "input-error" : ""}`} value={password} onChange={(e) => setPassword(e.target.value)} autoComplete="new-password"/>
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
                                <button type="button" className="btn btn-primary" onClick={onSubmit}>Enregistrer</button>

                                <button type="button" className="btn btn-error" onClick={onDelete}>Supprimer le compte</button>
                            </div>
                        </form>
                    )}
                </section>
            </main>
            <Footer />


            <dialog id="confirm_modal" className="modal modal-bottom sm:modal-middle">
                <div className="modal-box">
                    <form method="dialog">
                        <button className="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
                    </form>
                    <h3 className="font-bold text-lg">Confirmer les modifications</h3>
                    <p className="mb-2 text-sm">Pour enregistrer les changements de votre profil, merci de saisir votre mot de passe actuel</p>

                    <form onSubmit={handleConfirmUpdate}>
                       <div className="form-control mb-4">
                           <label className="label">
                               <span className="label-text">Mot de passe actuel</span>
                           </label>
                           <input type="password" className="input input-bordered w-full" value={currentPassword} onChange={(e) => setCurrentPassword(e.target.value)} autoComplete="current-password"/>
                           {confirmError && (
                               <label className="label">
                                   <span className="label-text-alt text-error">
                                        {confirmError}
                                    </span>
                               </label>
                           )}
                       </div>

                        <div className="modal-action">
                            <button type="button" className="btn" onClick={() => document.getElementById('confirm_modal')?.close()}>
                                Annuler
                            </button>
                            <button type="submit" className={`btn btn-primary ${saving ? "loading" : ""}`} disabled={saving}>
                                {saving ? "Enregistrement…" : "Confirmer"}
                            </button>
                        </div>
                    </form>
                </div>
            </dialog>

            <dialog id="deleteModal" className="modal modal-bottom sm:modal-middle">
                <div className="modal-box">
                    <form method="dialog">
                        <button className="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
                    </form>

                    <h3 className="font-bold text-lg text-error">Confirmer la suppression</h3>
                    <p className="mb-2 text-sm">
                        Cette action est <strong>définitive</strong>. Pour confirmer la suppression de votre compte,
                        entrez votre mot de passe actuel.
                    </p>

                    <form onSubmit={handleConfirmDelete}>
                        <div className="form-control mb-4">
                            <label className="label">
                                <span className="label-text">Mot de passe actuel</span>
                            </label>
                            <input type="password" className="input input-bordered w-full" value={currentPassword} onChange={(e) => setCurrentPassword(e.target.value)} autoComplete="current-password"/>
                            {deleteError && (
                                <label className="label">
                                    <span className="label-text-alt text-error">
                                        {deleteError}
                                    </span>
                                </label>
                            )}
                        </div>

                        <div className="modal-action">
                            <button type="button" className="btn" onClick={() => document.getElementById("deleteModal")?.close()}>
                                Annuler
                            </button>
                            <button type="submit" className={`btn btn-error ${deleting ? "loading" : ""}`} disabled={deleting}>
                                {deleting ? "Suppression…" : "Confirmer la suppression"}
                            </button>
                        </div>
                    </form>
                </div>
            </dialog>
        </>
    )
}

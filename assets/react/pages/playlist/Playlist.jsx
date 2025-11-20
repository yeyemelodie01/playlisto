import {useEffect, useState} from "react";
import {Link, useLocation} from "react-router-dom";
import apiService from "@services/apiService";
import Header from "@components/Header";
import MenuAside from "@components/MenuAside";
import Footer from "@components/Footer";

export default function Playlist() {
    const [playlists, setPlaylists] = useState([]);
    const [error, setError] = useState(null);
    const location = useLocation();
    const [flash, setFlash] = useState(null);

    useEffect(() => {
        document.title = 'Playlist - Playlisto';

        if (location.state?.message) {
            setFlash({
                message: location.state.message,
                type: location.state.type || "success",
            });

            window.history.replaceState(
                { ...window.history.state, usr: { ...location.state, message: undefined, type: undefined } },
                ""
            );
        }

        const fetchPlaylists = async () => {
            setError(null);
            try {
                const response = await apiService.get('/api/me/playlists', {});
                console.log('[Playlist] fetched data:', response.data);
                const payload = response.data;
                const items = Array.isArray(payload)
                    ? payload
                    : (payload?.['hydra:member'] ?? []);

                console.log('[Playlist] normalized items:', items);

                const sorted= [...items].sort((a, b) => {
                    const da = new Date(a.createdAt);
                    const db = new Date(b.createdAt);
                    return db - da;
                });

                setPlaylists(sorted);
            } catch (e) {
                console.error('[Playlist] fetch error:', e);
                setError("Une erreur est survenue lors du chargement de la playlist.");
            }
        }
        fetchPlaylists();
    }, [location.state]);

    useEffect(() => {
        console.log('[Playlist] state updated:', playlists);
    }, [playlists]);

    return (
        <>
            <Header />
            <main className="md:h-[34rem] h-[34.4rem] grid lg:grid-cols-5 sm:grid-cols-3 gap-4">
                <MenuAside />

                <section className="col-span-4 w-full mx-auto px-4 overflow-auto mt-4">
                    <h1 className="text-2xl font-semibold text-center">
                        Mes Playlist générée
                    </h1>

                    {flash && (
                        <div className={`alert mt-4 ${flash.type === "success" ? "alert-success" : flash.type === "error" ? "alert-error" : "alert-info"}`}>
                            <span>{flash.message}</span>
                        </div>
                    )}

                    {error && <p className="text-red-600">{error}</p>}

                    {!error && playlists.length > 0 && (
                        <ul className="flex flex-wrap gap-6 justify-center mt-6">
                            {playlists.map(playlist => (
                                <li key={playlist.id} className="w-64">
                                    <Link
                                        to={`/playlist/${playlist.id}`}
                                        className="block h-32 w-full bg-base-100 rounded-lg shadow hover:shadow-md transition p-4 border border-base-300 hover:border-primary cursor-pointer"
                                    >
                                        <h2 className="text-lg font-semibold mb-2">{playlist.title ?? `Playlist #${playlist.id}`}</h2>
                                        <p className="text-sm mb-1 opacity-70">{playlist.trackCount ?? 0} tracks</p>
                                        {playlist.createdAt && (
                                            <p className="text-xs text-gray-500">Created: {new Date(playlist.createdAt).toLocaleString()}</p>
                                        )}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                    {!error && playlists.length === 0 && (
                        <p className="text-center mt-4">Aucune playlist disponible.</p>
                    )}
                </section>
            </main>
            <Footer />
        </>
    )
}

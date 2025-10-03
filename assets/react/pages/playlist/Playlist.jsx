import {useEffect, useState} from "react";
import apiService from "@services/apiService";
import Header from "@components/Header";
import MenuAside from "@components/MenuAside";

export default function Playlist() {
    const [playlists, setPlaylists] = useState([]);
    const [error, setError] = useState(null);

    useEffect(() => {
        document.title = 'Playlist - Playlisto';

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
                setPlaylists(items);
            } catch (e) {
                console.error('[Playlist] fetch error:', e);
                setError("Une erreur est survenue lors du chargement de la playlist.");
            }
        }
        fetchPlaylists();
    }, []);

    useEffect(() => {
        console.log('[Playlist] state updated:', playlists);
    }, [playlists]);

    return (
        <>
            <Header />
            <main className="min-h-[calc(100vh-10rem)] grid lg:grid-cols-5 sm:grid-cols-3 gap-4">
                <MenuAside />

                <div className="max-w-3xl mx-auto px-4">
                        <h1 className="text-2xl font-semibold">
                            Playlist
                        </h1>
                        <p className="text-sm opacity-70">
                            Votre playlist personnalisée.
                        </p>
                    <section>
                        {error && <p className="text-red-600">{error}</p>}
                        {!error && playlists.length > 0 && (
                            <ul>
                                {playlists.map(playlist => (
                                    <li key={playlist.id} className="p-4 border rounded mb-4 bg-base-100">
                                        <div className="flex items-start justify-between">
                                            <h2 className="text-lg font-semibold">{playlist.title ?? `Playlist #${playlist.id}`}</h2>
                                            <span className="text-sm opacity-70">{playlist.trackCount ?? 0} tracks</span>
                                        </div>
                                        {playlist.description && (
                                            <p className="mt-1 text-sm opacity-80">{playlist.description}</p>
                                        )}
                                        <div className="mt-2 text-xs opacity-70">
                                            {playlist.mood && <span className="mr-3 italic">mood: {playlist.mood}</span>}
                                            {playlist.activity && <span className="italic">activity: {playlist.activity}</span>}
                                        </div>
                                        {playlist.createdAt && (
                                            <div className="mt-1 text-xs opacity-60">
                                                created: {new Date(playlist.createdAt).toLocaleString()}
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                        {!error && playlists.length === 0 && (
                            <p>Aucune playlist disponible.</p>
                        )}
                    </section>
                </div>
            </main>
        </>
    )
}
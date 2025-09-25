const MainSection = ({ title, description }) => {
    return (
        <section className="lg:col-span-4 sm:col-span-2 h-full p-6">
            <h1 className="text-3xl font-bold mb-4">{ title }</h1>
            <p className="text-lg">{ description }</p>
        </section>
    )
}

export default MainSection;
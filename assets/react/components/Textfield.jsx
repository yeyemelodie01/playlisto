import React from "react";

const Textfield = ({
                       type = "text",
                       placeholder,
                       size = "md",
                       value,
                       onChange,
                       isError = false,
                       errorCaption = "",
                       required = false,
                   }) => {
    const sizeClasses = {
        sm: "text-sm h-8",
        md: "text-sm h-10",
    };

    return (
        <div className="form-control w-full mb-4">
            <input
                type={type}
                placeholder={placeholder}
                value={value}
                onChange={onChange}
                required={required}
                className={`input input-${size} w-full ${isError ? "input-error" : "input-bordered"}`}
            />
            {isError && errorCaption && (
                <label className="label">
                    <span className="label-text-alt text-error">{errorCaption}</span>
                </label>
            )}
        </div>
    );
};

export default Textfield;
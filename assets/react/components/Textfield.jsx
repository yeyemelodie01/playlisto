import React from "react";

const Textfield = ({
                       type = "text",
                       placeholder,
                       value,
                       onChange,
                       isError = false,
                       errorCaption = "",
                       required = false,
                       inputClass = "",
                   }) => {

    return (
        <div className="form-control w-full">
            <input
                type={type}
                placeholder={placeholder}
                value={value}
                onChange={onChange}
                required={required}
                className={`${inputClass} ${isError ? "input-error" : "input-bordered"}`}
            />
            {isError && errorCaption && (
                <label className="label">
                    <span className="label-text-alt text-red-500">{errorCaption}</span>
                </label>
            )}
        </div>
    );
};

export default Textfield;